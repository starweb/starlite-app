<?php declare(strict_types=1);
/**
 * Starlit App.
 *
 * @copyright Copyright (c) 2016 Starweb AB
 * @license   BSD 3-Clause
 */

namespace Starlit\App;

use Starlit\App\Container\Container;
use Starlit\App\Provider\BootableServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Starlit\App\Provider\ServiceProviderInterface;
use Starlit\App\Provider\StandardServiceProvider;
use Starlit\App\Provider\ErrorServiceProvider;

class BaseApp extends Container
{
    /**
     * @const string
     */
    const CHARSET = 'UTF-8';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var ServiceProviderInterface[]
     */
    protected $providers = [];

    /**
     * @var bool
     */
    protected $booted = false;

    /**
     * @var bool
     */
    protected $isCli = false;

    /**
     * Constructor.
     *
     * @param array|Config $config
     * @param string       $environment Defaults to "production"
     */
    public function __construct(array $config = [], string $environment = 'production')
    {
        if ($config instanceof Config) {
            $this->config = $config;
        } else {
            $this->config = new Config($config);
        }

        $this->environment = $environment;

        $this->init();
    }

    /**
     * Initializes the application object.

     * Override and put initialization code that should always be run as early as
     * possible here, but make sure no objects are actually instanced here, because then
     * mock objects can't be injected in their place. Place object instance code in
     * the preHandle method.
     */
    protected function init(): void
    {
        $this->isCli = (PHP_SAPI === 'cli');

        if ($this->config->has('phpSettings')) {
            $this->setPhpSettings($this->config->get('phpSettings'));
        }

        $this->registerProviders();
    }

    protected function registerProviders(): void
    {
        $this->register(new ErrorServiceProvider());
        $this->register(new StandardServiceProvider());
    }

    public function register(ServiceProviderInterface $provider): void
    {
        $this->providers[] = $provider;

        $provider->register($this);
    }

    protected function setPhpSettings(array $phpSettings, string $prefix = ''): void
    {
        foreach ($phpSettings as $key => $val) {
            $key = $prefix . $key;
            if (\is_scalar($val)) {
                \ini_set($key, $val);
            } elseif (\is_array($val)) {
                $this->setPhpSettings($val, $key . '.'); // Set sub setting with a recursive call
            }
        }
    }

    /**
     * Boot the application and its service providers.
     *
     * This is normally called by handle(). If requests are not handled
     * this method will have to called manually to boot.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            if ($provider instanceof BootableServiceProviderInterface) {
                $provider->boot($this);
            }
        }

        $this->booted = true;
    }

    /**
     * Pre handle method meant to be overridden in descendant classes (optional).
     *
     * This method is called before an request is handled. Object instance code should be
     * place here and not in init() (more info about this at init()).
     *
     * @param Request $request
     * @return Response|null
     */
    protected function preHandle(Request $request): ?Response
    {
        return null;
    }

    /**
     * Post route method meant to be overridden in descendant classes (optional).
     * This method is called before an request is dispatched  but after it's routed. This means that  we know
     * it's a valid route and have access to the route attributes at this stage.
     *
     * @param Request $request
     * @return Response|null
     */
    protected function postRoute(Request $request): ?Response
    {
        return null;
    }

    /**
     * Handles an http request and returns a response.
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $this->alias('request', Request::class);
        $this->set(Request::class, $request);

        $this->boot();

        if (($preHandleResponse = $this->preHandle($request))) {
            return $preHandleResponse;
        }

        try {
            $controller = $this->get(RouterInterface::class)->route($request);

            if (($postRouteResponse = $this->postRoute($request))) {
                return $postRouteResponse;
            }

            $response = $controller->dispatch();
        } catch (ResourceNotFoundException $e) {
            $response = $this->getNoRouteResponse($request);
        }

        $this->postHandle($request);

        return $response;
    }

    protected function getNoRouteResponse(Request $request): Response
    {
        return new Response('Not Found', 404);
    }

    /**
     * Post handle method meant to be overridden in descendant classes (optional).
     * This method is called after an request has been handled but before
     * the response is returned from the handle method.
     *
     * @param Request $request
     */
    protected function postHandle(Request $request): void
    {
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function isCli(): bool
    {
        return $this->isCli;
    }

    public function getRequest(): ?Request
    {
        return $this->has(Request::class) ? $this->get(Request::class) : null;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }
}
