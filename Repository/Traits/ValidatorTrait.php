<?php

namespace Dothiv\Bundle\ContentfulBundle\Repository\Traits;

use Dothiv\Bundle\ContentfulBundle\Exception\InvalidArgumentException;
use Symfony\Component\Validator\ValidatorInterface;

trait ValidatorTrait
{

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @param object     $entity
     * @param array|null $groups The validation groups to validate.
     *
     * @throws InvalidArgumentException if $entity is invalid
     * @return object $entity
     */
    protected function validate($entity, array $groups = null)
    {
        $errors = $this->validator->validate($entity, $groups);
        if (count($errors) != 0) {
            throw new InvalidArgumentException((string)$errors);
        }
        return $entity;
    }

    /**
     * @param ValidatorInterface $validator
     */
    public function setValidator(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }
}
