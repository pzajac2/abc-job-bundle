<?php
/*
* This file is part of the job-bundle package.
*
* (c) Hannes Schulz <hannes.schulz@aboutcoders.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Abc\Bundle\JobBundle\Request;

use JMS\Serializer\DeserializationContext;
use JMS\Serializer\Exception\Exception;
use JMS\Serializer\Exception\UnsupportedFormatException;
use JMS\Serializer\SerializerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Hannes Schulz <hannes.schulz@aboutcoders.com>
 */
class JobParamConverter implements ParamConverterInterface
{
    /**
     * @var array
     */
    private $context = [];

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var string
     */
    protected $validationErrorsArgument;

    /**
     * @param SerializerInterface $serializer
     * @param ValidatorInterface  $validator
     * @throws \InvalidArgumentException
     */
    public function __construct(SerializerInterface $serializer, ValidatorInterface $validator = null)
    {
        $this->serializer               = $serializer;
        $this->validator                = $validator;
        $this->validationErrorsArgument = 'validationErrors';
    }

    /**
     * {@inheritDoc}
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $options = (array)$configuration->getOptions();

        if (isset($options['deserializationContext']) && is_array($options['deserializationContext'])) {
            $arrayContext = array_merge($this->context, $options['deserializationContext']);
        } else {
            $arrayContext = $this->context;
        }
        $this->configureContext($context = new DeserializationContext(), $arrayContext);

        try {
            $object = $this->serializer->deserialize(
                json_encode($request->request->all(), true),
                $configuration->getClass(),
                $request->getContentType(),
                $context
            );

        } catch (UnsupportedFormatException $e) {
            throw new UnsupportedMediaTypeHttpException($e->getMessage(), $e);
        } catch (Exception $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $request->attributes->set($configuration->getName(), $object);

        if (null !== $this->validator) {
            $validatorOptions = $this->getValidatorOptions($options);

            $errors = $this->validator->validate($object, null, $validatorOptions['groups']);

            $request->attributes->set(
                $this->validationErrorsArgument,
                $errors
            );
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supports(ParamConverter $configuration)
    {
        return true;
    }

    /**
     * @param DeserializationContext $context
     * @param array                  $options
     */
    protected function configureContext(DeserializationContext $context, array $options)
    {
        foreach ($options as $key => $value) {
            if ($key === 'groups') {
                $context->setGroups($options['groups']);
            } elseif ($key === 'version') {
                $context->setVersion($options['version']);
            } else {
                $context->setAttribute($key, $value);
            }
        }
    }

    /**
     * @param array $options
     * @return array
     */
    private function getValidatorOptions(array $options)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'groups'   => null,
            'traverse' => false,
            'deep'     => false,
        ]);

        return $resolver->resolve(isset($options['validator']) ? $options['validator'] : []);
    }
}