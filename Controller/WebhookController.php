<?php

namespace Dothiv\Bundle\ContentfulBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectManager;
use Dothiv\Bundle\ContentfulBundle\Adapter\ContentfulContentTypeReader;
use Dothiv\Bundle\ContentfulBundle\Adapter\ContentfulEntityReader;
use Dothiv\Bundle\ContentfulBundle\DothivContentfulBundleEvents;
use Dothiv\Bundle\ContentfulBundle\Event\ContentfulAssetEvent;
use Dothiv\Bundle\ContentfulBundle\Event\ContentfulContentTypeEvent;
use Dothiv\Bundle\ContentfulBundle\Event\ContentfulEntryEvent;
use Dothiv\Bundle\ContentfulBundle\Event\DeletedContentfulEntryEvent;
use Dothiv\Bundle\ContentfulBundle\Item\ContentfulContentType;
use Dothiv\Bundle\ContentfulBundle\Logger\LoggerAwareTrait;
use Dothiv\Bundle\ContentfulBundle\Repository\ContentfulContentTypeRepository;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class WebhookController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        ContentfulContentTypeRepository $contentTypeRepo,
        EventDispatcherInterface $dispatcher,
        ObjectManager $em)
    {
        $this->contentTypeRepo = $contentTypeRepo;
        $this->dispatcher      = $dispatcher;
        $this->em              = $em;
    }

    public function webhookAction(Request $request)
    {
        $topic = $request->headers->get('X-Contentful-Topic');
        if (!$topic) {
            $this->log('Missing X-Contentful-Topic header!');
            throw new BadRequestHttpException(sprintf(
                'Missing X-Contentful-Topic header!'
            ));
        }
        $data = json_decode($request->getContent());
        if (!is_object($data) || !property_exists($data, 'sys')) {
            throw new BadRequestHttpException('JSON object expected.');
        }
        $id      = $data->sys->id;
        $spaceId = $data->sys->space->sys->id;
        $this->log('%s Webhook called for space %s: type: %s, id: %s', $topic, $spaceId, $data->sys->type, $id);

        switch ($topic) {
            case 'ContentManagement.ContentType.publish':
                $reader      = new ContentfulContentTypeReader($spaceId);
                $contentType = $reader->getContentType($data);
                $this->dispatcher->dispatch(
                    DothivContentfulBundleEvents::CONTENT_TYPE_SYNC,
                    new ContentfulContentTypeEvent($contentType)
                );
                break;
            case 'ContentManagement.ContentType.unpublish':
                // TODO: Implement "ContentManagement.ContentType.unpublish"
                break;
            case 'ContentManagement.Entry.publish':
                $reader = new ContentfulEntityReader($spaceId, $this->getContentTypes($spaceId));
                $entry  = $reader->getEntry($data);
                $this->dispatcher->dispatch(
                    DothivContentfulBundleEvents::ENTRY_SYNC,
                    new ContentfulEntryEvent($entry)
                );
                break;
            case 'ContentManagement.Entry.unpublish':
                $reader = new ContentfulEntityReader($spaceId, $this->getContentTypes($spaceId));
                $entry  = $reader->getEntry($data);
                $this->dispatcher->dispatch(
                    DothivContentfulBundleEvents::ENTRY_DELETE,
                    new DeletedContentfulEntryEvent($entry)
                );
                break;
            case 'ContentManagement.Asset.publish':
                $reader = new ContentfulEntityReader($spaceId, $this->getContentTypes($spaceId));
                $entry  = $reader->getEntry($data);
                $this->dispatcher->dispatch(
                    DothivContentfulBundleEvents::ASSET_SYNC,
                    new ContentfulAssetEvent($entry)
                );
                break;
            case 'ContentManagement.Asset.unpublish':
                // TODO: Implement "ContentManagement.Asset.unpublish"
                break;
        }

        $this->em->flush();

        $response = new Response('', 204);
        return $response;
    }

    /**
     * @param string $spaceId
     *
     * @return ArrayCollection|ContentfulContentType[]
     */
    protected function getContentTypes($spaceId)
    {
        $ctypes = new ArrayCollection();
        foreach ($this->contentTypeRepo->findAllBySpaceId($spaceId) as $c) {
            $ctypes->set($c->getId(), $c);
        }
        return $ctypes;
    }
} 
