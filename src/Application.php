<?php
namespace groupcash\php\cli;

use groupcash\php\Groupcash;
use groupcash\php\io\Serializer;
use groupcash\php\io\transcoders\Base64Transcoder;
use groupcash\php\io\transcoders\NoneTranscoder;
use groupcash\php\io\transcoders\HexadecimalTranscoder;
use groupcash\php\io\transcoders\JsonTranscoder;
use groupcash\php\io\transcoders\MsgPackTranscoder;
use groupcash\php\io\transformers\AuthorizationTransformer;
use groupcash\php\io\transformers\CoinTransformer;
use groupcash\php\algorithms\EccAlgorithm;
use rtens\domin\delivery\cli\CliApplication;
use rtens\domin\delivery\cli\Console;
use rtens\domin\reflection\GenericMethodAction;

class Application {

    /** @var Groupcash */
    private $lib;

    /** @var Serializer */
    private $serializer;

    public function __construct() {
        $this->lib = new Groupcash(new EccAlgorithm());
        $this->serializer = (new Serializer())
            ->addTransformer(new CoinTransformer())
            ->addTransformer(new AuthorizationTransformer());

        if (MsgPackTranscoder::isAvailable()) {
            $this->serializer
                ->registerTranscoder('msgpack64', new Base64Transcoder(new MsgPackTranscoder()))
                ->registerTranscoder('msgpack16', new HexadecimalTranscoder(new MsgPackTranscoder()))
                ->registerTranscoder('msgpack', new MsgPackTranscoder());
        }

        $this->serializer
            ->registerTranscoder('json', new JsonTranscoder())
            ->registerTranscoder('hexJson', new JsonTranscoder(new HexadecimalTranscoder(new NoneTranscoder())));
    }

    public function run() {
        global $argv;
        $console = new Console($argv);

        CliApplication::run(CliApplication::init(function (CliApplication $app) use ($console) {
            $this->setUpCliApplication($app, $console);
        }), $console);
    }

    private function setUpCliApplication(CliApplication $app, Console $console) {
        $serializingField = new SerializingField($this->serializer);

        $app->fields->add($serializingField);
        $app->fields->add(new SerializingArrayField($serializingField, $app->fields));
        $app->fields->add(new FractionField());
        $app->renderers->add(new SerializingRenderer($this->serializer, $console));

        $transcoders = [
            'base64' => new Base64Transcoder(new NoneTranscoder()),
            'hex' => new HexadecimalTranscoder(new NoneTranscoder()),
            'none' => new NoneTranscoder(),
        ];
        $app->fields->add(new BinaryField($transcoders));
        $app->renderers->add(new BinaryRenderer($console, $transcoders));

        $this->addLibraryActions($app);
        $this->addDecodeAction($app);
    }

    private function addLibraryActions(CliApplication $app) {
        foreach ((new \ReflectionClass($this->lib))->getMethods() as $method) {
            if (!$method->isPublic() || $method->isConstructor()) {
                continue;
            }
            $app->actions->add($method->getName(),
                new GenericMethodAction($this->lib, $method->getName(), $app->types, $app->parser));
        }
    }

    private function addDecodeAction(CliApplication $app) {
        $app->actions->add('decode',
            (new GenericMethodAction($this, 'decode', $app->types, $app->parser))->generic()
                ->setCaption('Decode')
                ->setDescription('Displays an object in human-readable form'));
        $app->actions->add('transcode',
            (new GenericMethodAction($this, 'transcode', $app->types, $app->parser))->generic()
                ->setCaption('Transcode')
                ->setDescription('Changes the encoding of an object'));
    }

    /**
     * @param string $encoded
     * @return string
     * @throws \Exception
     */
    public function decode($encoded) {
        if (substr($encoded, 0, 1) == '@') {
            $encoded = trim(file_get_contents(substr($encoded, 1)));
        }

        return json_encode($this->serializer->decode($encoded), JSON_PRETTY_PRINT);
    }

    /**
     * @param string $encoded
     * @return object
     */
    public function transcode($encoded) {
        if (substr($encoded, 0, 1) == '@') {
            $encoded = trim(file_get_contents(substr($encoded, 1)));
        }

        return $this->serializer->inflate($encoded);
    }
}