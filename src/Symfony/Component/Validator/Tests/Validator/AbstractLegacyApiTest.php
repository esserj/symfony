<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Validator;

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ExecutionContextInterface;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Fixtures\Reference;
use Symfony\Component\Validator\ValidatorInterface as LegacyValidatorInterface;

/**
 * Verifies that a validator satisfies the API of Symfony < 2.5.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractLegacyApiTest extends AbstractValidatorTest
{
    /**
     * @var LegacyValidatorInterface
     */
    protected $validator;

    /**
     * @param MetadataFactoryInterface $metadataFactory
     *
     * @return LegacyValidatorInterface
     */
    abstract protected function createValidator(MetadataFactoryInterface $metadataFactory);

    protected function setUp()
    {
        parent::setUp();

        $this->validator = $this->createValidator($this->metadataFactory);
    }

    protected function validate($value, $constraints, $groups = null)
    {
        return $this->validator->validateValue($value, $constraints, $groups);
    }

    protected function validateObject($object, $groups = null)
    {
        return $this->validator->validate($object, $groups);
    }

    protected function validateCollection($collection, $groups = null, $deep = false)
    {
        return $this->validator->validate($collection, $groups, true, $deep);
    }

    protected function validateProperty($object, $propertyName, $groups = null)
    {
        return $this->validator->validateProperty($object, $propertyName, $groups);
    }

    protected function validatePropertyValue($object, $propertyName, $value, $groups = null)
    {
        return $this->validator->validatePropertyValue($object, $propertyName, $value, $groups);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\NoSuchMetadataException
     */
    public function testTraversableTraverseDisabled()
    {
        $test = $this;
        $entity = new Entity();
        $traversable = new \ArrayIterator(array('key' => $entity));

        $callback = function () use ($test) {
            $test->fail('Should not be called');
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $this->validator->validate($traversable, 'Group');
    }

    public function testValidateInContext()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $context->validate($value->reference, 'subpath');
        };

        $callback2 = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $test->assertSame($test::REFERENCE_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('subpath', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->referenceMetadata, $context->getMetadata());
            $test->assertSame($test->metadataFactory, $context->getMetadataFactory());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame($entity->reference, $context->getValue());
            $test->assertSame($entity->reference, $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback1,
            'groups' => 'Group',
        )));
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group',
        )));

        $violations = $this->validator->validate($entity, 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getMessageParameters());
        $this->assertSame('subpath', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getMessagePluralization());
        $this->assertNull($violations[0]->getCode());
    }

    public function testValidateArrayInContext()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $context->validate(array('key' => $value->reference), 'subpath');
        };

        $callback2 = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $test->assertSame($test::REFERENCE_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('subpath[key]', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->referenceMetadata, $context->getMetadata());
            $test->assertSame($test->metadataFactory, $context->getMetadataFactory());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame($entity->reference, $context->getValue());
            $test->assertSame($entity->reference, $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback1,
            'groups' => 'Group',
        )));
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group',
        )));

        $violations = $this->validator->validate($entity, 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getMessageParameters());
        $this->assertSame('subpath[key]', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getMessagePluralization());
        $this->assertNull($violations[0]->getCode());
    }

    public function testAddCustomizedViolation()
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation(
                'Message %param%',
                array('%param%' => 'value'),
                'Invalid value',
                2,
                'Code'
            );
        };

        $this->metadata->addConstraint(new Callback($callback));

        $violations = $this->validateObject($entity);

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getMessageParameters());
        $this->assertSame('', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Invalid value', $violations[0]->getInvalidValue());
        $this->assertSame(2, $violations[0]->getMessagePluralization());
        $this->assertSame('Code', $violations[0]->getCode());
    }

    public function testGetMetadataFactory()
    {
        $this->assertSame($this->metadataFactory, $this->validator->getMetadataFactory());
    }
}
