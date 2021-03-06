<?php
namespace groupcash\php\cli;

use groupcash\php\io\Transcoder;
use groupcash\php\model\signing\Binary;
use rtens\domin\delivery\cli\CliField;
use rtens\domin\Parameter;
use watoki\reflect\type\ClassType;

class BinaryField implements CliField {

    /** @var Transcoder[] */
    private $transcoders;

    /**
     * @param Transcoder[] $transcoders
     */
    public function __construct(array $transcoders) {
        $this->transcoders = $transcoders;
    }

    /**
     * @param Parameter $parameter
     * @return null|string
     */
    public function getDescription(Parameter $parameter) {
        return null;
    }

    /**
     * @param Parameter $parameter
     * @return bool
     */
    public function handles(Parameter $parameter) {
        return $parameter->getType() == new ClassType(Binary::class);
    }

    /**
     * @param Parameter $parameter
     * @param string $serialized
     * @return mixed
     * @throws \Exception
     */
    public function inflate(Parameter $parameter, $serialized) {
        if (substr($serialized, 0, 1) == '@') {
            $serialized = trim(file_get_contents(substr($serialized, 1)));

            if (strpos($serialized, 'KEY-')) {
                $serialized = str_replace("\n", '', $serialized);
                $serialized = preg_replace('/.*KEY-+([^\-]+)-+END.*/', '$1', $serialized);
                $serialized = base64_decode($serialized);
            }
        }

        foreach ($this->transcoders as $transcoder) {
            if ($transcoder->hasEncoded($serialized)) {
                return new Binary($transcoder->decode($serialized));
            }
        }

        throw new \Exception('Unknown encoding.');
    }
}