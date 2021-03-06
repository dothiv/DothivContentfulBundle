<?php

namespace Dothiv\Bundle\ContentfulBundle\Adapter;

use Dothiv\Bundle\ContentfulBundle\Exception\InvalidArgumentException;
use Dothiv\Bundle\ContentfulBundle\Exception\RuntimeException;
use Dothiv\Bundle\ContentfulBundle\Item\ContentfulAsset;
use Dothiv\Bundle\ContentfulBundle\Logger\LoggerAwareTrait;
use Dothiv\Bundle\ContentfulBundle\Repository\ContentfulAssetRepositoryInterface;
use Symfony\Component\HttpFoundation\File\MimeType\ExtensionGuesser;

class FilesystemAssetAdapter implements ContentfulAssetAdapterInterface
{
    use LoggerAwareTrait;

    /**
     * @var ContentfulAssetRepositoryInterface
     */
    private $assetRepo;

    /**
     * @var string
     */
    private $webPath;

    /**
     * @var string
     */
    private $localPath;

    /**
     * @param string                    $webPath
     * @param string                    $localPath
     * @param ContentfulAssetRepositoryInterface $assetRepo
     */
    public function __construct($webPath, $localPath, ContentfulAssetRepositoryInterface $assetRepo)
    {
        $this->webPath   = rtrim($webPath, '/');
        $this->localPath = rtrim($localPath, '/');
        $this->assetRepo = $assetRepo;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoute(ContentfulAsset $asset, $locale)
    {
        return $this->getFilename($asset, $this->webPath, $locale);
    }

    protected function getFilename(ContentfulAsset $asset, $prefix, $locale)
    {
        $extension        = $this->getExtension($asset, $locale);
        $originalFilename = $asset->file[$locale]['fileName'];
        list($name,) = explode('.', $originalFilename);
        $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        $name = preg_replace('/[^A-Za-z0-9-_]/', '', $name);
        return sprintf(
            '%s/%s/%s-%s-%s-%d.%s',
            $prefix,
            trim($asset->getSpaceId(), '/'),
            $name,
            $asset->getId(),
            $locale,
            $asset->getRevision(),
            $extension
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getLocalFile(ContentfulAsset $asset, $locale)
    {
        return new \SplFileInfo($this->getFilename($asset, $this->localPath, $locale));
    }

    /**
     * @param ContentfulAsset $asset
     * @param string          $locale
     *
     * @return string|null
     * @throws InvalidArgumentException
     */
    protected function getExtension(ContentfulAsset $asset, $locale)
    {
        $asset   = $this->assetRepo->findNewestById($asset->getSpaceId(), $asset->getId())->getOrCall(function () use ($asset) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot find asset with id "%s" in space "%s"!',
                    $asset->getId(),
                    $asset->getSpaceId()
                )
            );
        });
        $guesser = ExtensionGuesser::getInstance();
        return $guesser->guess($asset->file[$locale]['contentType']);
    }

    /**
     * Caches the asset to the local filesystem.
     *
     * @param ContentfulAsset $asset
     *
     * @return void
     * @throws RuntimeException
     */
    public function cache(ContentfulAsset $asset)
    {
        if (!isset($asset->file)) {
            $this->log('Asset %s has no file.', $asset);
            return;
        }
        foreach ($asset->file as $locale => $file) {
            if (!$file) {
                // File not published.
                $this->log('Asset %s contains unpublished file for %s.', $asset, $locale);
                continue;
            }
            $localFile = $this->getLocalFile($asset, $locale);
            if ($localFile->isFile()) {
                continue;
            }
            $this->log(
                'Caching "%s" file for asset "%s" as "%s" ...',
                $locale,
                $asset->getId(),
                $localFile->getPathname()
            );
            $dir = new \SplFileInfo($localFile->getPath());
            if (!$dir->isWritable()) {
                throw new RuntimeException(
                    sprintf(
                        'Target directory "%s" is not writeable!',
                        $localFile->getPath()
                    )
                );
            }
            copy(str_replace('//', 'https://', $file['url']), $localFile->getPathname());
            $size = filesize($localFile->getPathname());
            $this->log(
                '%d bytes saved.',
                $size
            );
        }
    }
}
