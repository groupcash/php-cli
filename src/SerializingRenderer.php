<?php
namespace groupcash\php\cli;

use groupcash\php\io\Serializer;
use groupcash\php\model\Coin;
use rtens\domin\delivery\cli\Console;
use rtens\domin\delivery\Renderer;

class SerializingRenderer implements Renderer {

    /** @var Serializer */
    private $serializer;

    /** @var Console */
    private $console;

    /**
     * @param Serializer $serializer
     * @param Console $console
     */
    public function __construct($serializer, Console $console) {
        $this->serializer = $serializer;
        $this->console = $console;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function handles($value) {
        return $this->serializer->handles($value);
    }

    /**
     * @param Coin $value
     * @return mixed
     * @throws \Exception
     */
    public function render($value) {
        $transcoder = $this->getTranscoderKey();
        return $this->serializer->serialize($value, $transcoder);
    }

    /**
     * @return string
     */
    private function getTranscoderKey() {
        $keys = $this->serializer->getTranscoderKeys();

        if ($this->console->getArguments() == ['!']) {
            $this->console->writeLine('Available encodings: ' . implode(', ', $keys));
            return $this->console->read("Encoding [{$keys[0]}]: ") ?: $keys[0];
        }

        try {
            return $this->console->getOption('encoding');
        } catch (\Exception $e) {
            return $keys[0];
        }
    }
}