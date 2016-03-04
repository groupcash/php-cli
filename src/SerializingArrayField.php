<?php
namespace groupcash\php\cli;

use rtens\domin\delivery\cli\CliField;
use rtens\domin\delivery\FieldRegistry;
use rtens\domin\Parameter;
use watoki\reflect\type\ArrayType;

class SerializingArrayField implements CliField {

    const MARKER = '_NOT_SERIALZED_ARRAY_';

    /** @var SerializingField */
    private $serializing;

    /** @var FieldRegistry */
    private $fields;

    /**
     * @param SerializingField $serializing
     * @param FieldRegistry $fields
     */
    public function __construct(SerializingField $serializing, FieldRegistry $fields) {
        $this->serializing = $serializing;
        $this->fields = $fields;
    }

    /**
     * @param Parameter $parameter
     * @return null|string
     */
    public function getDescription(Parameter $parameter) {
        return '(size or file)';
    }

    /**
     * @param Parameter $parameter
     * @return bool
     */
    public function handles(Parameter $parameter) {
        $type = $parameter->getType();
        return $parameter->getDescription() != self::MARKER && $type instanceof ArrayType && $this->serializing->handles($parameter->withType($type->getItemType()));
    }

    /**
     * @param Parameter $parameter
     * @param string $serialized
     * @return mixed
     */
    public function inflate(Parameter $parameter, $serialized) {
        if (substr($serialized, 0, 1) == '@') {
            $array = explode("\n\n", trim(file_get_contents(substr($serialized, 1))));

            return array_map(function ($item) use ($parameter) {
                /** @var ArrayType $type */
                $type = $parameter->getType();
                $itemParameter = $parameter->withType($type->getItemType());

                return $this->fields->getField($itemParameter)->inflate($itemParameter, $item);
            }, $array);
        }

        $parameter = (new Parameter($parameter->getName(), $parameter->getType(), $parameter->isRequired()))
            ->setDescription(self::MARKER);

        return $this->fields->getField($parameter)->inflate($parameter, $serialized);
    }
}