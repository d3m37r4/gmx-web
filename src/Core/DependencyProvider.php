<?php

namespace GameX\Core;

use \Pimple\ServiceProviderInterface;
use \Pimple\Container as PimpleContainer;
use \Psr\Container\ContainerInterface;
use \GameX\Constants\PreferencesConstants;

use \GameX\Core\Configuration\Config;
use \GameX\Core\Configuration\Providers\DatabaseProvider;
use \GameX\Core\Configuration\Exceptions\CantLoadException;
use \GameX\Core\Configuration\Exceptions\NotFoundException;

use \GameX\Core\Session\Session;

use \GameX\Core\Cache\Cache;
use \GameX\Core\Cache\CacheDriverFactory;
use \GameX\Core\Cache\Items\Preferences;
use \GameX\Core\Cache\Items\Permissions as PermissionsCache;
use \GameX\Core\Cache\Items\PlayersOnline;

use \GameX\Core\Log\Logger;
use \Monolog\Formatter\LineFormatter;
use \Monolog\Handler\RotatingFileHandler;

use \Illuminate\Database\Capsule\Manager as DataBaseManager;
use \Illuminate\Events\Dispatcher as DataBaseDispatcher;

use \GameX\Core\Lang\Loaders\JSONLoader;
use \GameX\Core\Lang\Providers\SlimProvider;
use \GameX\Core\Lang\Language;
use \GameX\Core\Lang\Exceptions\BadLanguageException;
use \GameX\Core\Lang\Exceptions\CantReadException;

use \GameX\Core\Auth\Permissions;

use \GameX\Core\Auth\SentinelBootstrapper;
use \Cartalyst\Sentinel\Sentinel;

use \GameX\Constants\UserConstants;
use \GameX\Core\Auth\Social\SocialAuth;
use \GameX\Core\Auth\Social\Session as HybridauthSession;
use \GameX\Core\Auth\Social\Logger as HybridauthLogger;
use \GameX\Core\Auth\Social\CallbackHelper as HybridauthCallback;
use \Hybridauth\Provider\Steam as HybridauthSteamProvider;
use \Hybridauth\Provider\Vkontakte as VkontakteProvider;
use \Hybridauth\Provider\Facebook as FacebookProvider;
use \Hybridauth\Provider\Discord as DiscordProvider;

use \Slim\Views\Twig;
use \Slim\Views\TwigExtension;
use \GameX\Core\Update\TwigVersionExtension;
use \GameX\Core\CSRF\Extension as CSRFExtension;
use \GameX\Core\Auth\ViewExtension as AuthViewExtension;
use \GameX\Core\Lang\Extension\ViewExtension as LangViewExtension;
use \GameX\Core\AccessFlags\ViewExtension as AccessFlagsViewExtension;
use \GameX\Core\Upload\ViewExtension as UploadFlagsViewExtension;
use \GameX\Core\Constants\ViewExtension as ConstantsViewExtension;

use \GameX\Core\Mail\Helper as MailHelper;
use \GameX\Core\Mail\Helpers\SwiftMailer;

use \GameX\Core\CSRF\Token;

use \GameX\Core\Upload\Upload;

use \GameX\Core\Update\Updater;
use \GameX\Core\Update\Manifest;

use \GameX\Core\Breadcrumbs\Breadcrumbs;

use \GameX\Core\Utils\RconExec;

class DependencyProvider implements ServiceProviderInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * DependencyProvider constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function register(PimpleContainer $container)
    {
        $container['base_url'] = function (ContainerInterface $container) {
            /** @var \Slim\Http\Uri $uri */
            $uri = $container->get('request')->getUri();
            return rtrim(str_ireplace('index.php', '', $uri->getBasePath()), '/');
        };
        
        $container['config'] = $this->config;
        
        $container['preferences'] = function (ContainerInterface $container) {
            return $this->getPreferences($container);
        };
        
        $container['session'] = function () {
            return $this->getSession();
        };
        
        $container['cache'] = function (ContainerInterface $container) {
            return $this->getCache($container);
        };
        
        $container['log'] = function (ContainerInterface $container) {
            return $this->getLogger($container);
        };
        
        $container['db'] = function (ContainerInterface $container) {
            return $this->getDataBase($container);
        };
        
        $container['lang'] = function (ContainerInterface $container) {
            return $this->getLanguage($container);
        };
        
        $container['permissions'] = function (ContainerInterface $container) {
            return $this->getPermissions($container);
        };
        
        $container['auth'] = function (ContainerInterface $container) {
            return $this->getAuth($container);
        };
    
        $container['social'] = function (ContainerInterface $container) {
            return $this->getSocial($container);
        };
        
        $container['view'] = function (ContainerInterface $container) {
            return $this->getView($container);
        };
        
        $container['mail'] = function (ContainerInterface $container) {
            return $this->getMail($container);
        };
        
        $container['csrf'] = function (ContainerInterface $container) {
            return $this->getCSRF($container);
        };
        
        $container['flash'] = function (ContainerInterface $container) {
            return $this->getFlashMessages($container);
        };
        
        $container['upload'] = function (ContainerInterface $container) {
            return $this->getUpload($container);
        };

	    $container['manifest'] = function (ContainerInterface $container) {
		    return $this->getManifest($container);
	    };
        
        $container['updater'] = function (ContainerInterface $container) {
            return $this->getUpdater($container);
        };

        $container['breadcrumbs'] = function (ContainerInterface $container) {
            return $this->getBreadcrumbs($container);
        };
        
        $container['modules'] = function (ContainerInterface $container) {
            $modules = new \GameX\Core\Module\Module();
            //	$modules->addModule(new \GameX\Modules\TestModule\Module());
            return $modules;
        };

        $container['utils_rcon_exec'] = function (ContainerInterface $container) {
        	return new RconExec($container);
        };
    }
    
    /**
     * @param ContainerInterface $container
     * @return Config
     * @throws CantLoadException
     */
    public function getPreferences(ContainerInterface $container)
    {
        $provider = new DatabaseProvider($container->get('cache'));
        return new Config($provider);
    }
    
    /**
     * @return Session
     */
    public function getSession()
    {
        return new Session();
    }

    /**
     * @param ContainerInterface $container
     * @return Cache
     * @throws NotFoundException
     */
    public function getCache(ContainerInterface $container)
    {
        $driver = CacheDriverFactory::getDriver($container, 'runtime' . DIRECTORY_SEPARATOR . 'cache');
        $cache = new Cache($driver);
        $cache->add('preferences', new Preferences());
        $cache->add('permissions', new PermissionsCache());
        $cache->add('players_online', new PlayersOnline());
        return $cache;
    }
    
    /**
     * @param ContainerInterface $container
     * @return Logger
     */
    public function getLogger(ContainerInterface $container)
    {
        $formatter = new LineFormatter(null, null, true, true);
        
        $logPath = $container->get('root') . 'runtime' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'log.log';
        $handler = new RotatingFileHandler($logPath, 10, Logger::DEBUG);
        $handler->setFormatter($formatter);
        
        
        $logger = new Logger('gmx');
        $logger->pushHandler($handler);
        return $logger;
    }
    
    /**
     * @param ContainerInterface $container
     * @return DataBaseManager
     * @throws NotFoundException
     */
    public function getDataBase(ContainerInterface $container)
    {
        /** @var Config $config */
        $config = $container->get('config');
        
        $capsule = new DataBaseManager;
        $capsule->addConnection($config->getNode('db')->toArray());

	    $capsule->setEventDispatcher(new DataBaseDispatcher());
        
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        
        if ($config->getNode('log')->get('queries', false)) {
            $capsule->getConnection()->enableQueryLog();
        }
        
        return $capsule;
    }
    
    /**
     * @param ContainerInterface $container
     * @return Language
     * @throws BadLanguageException
     * @throws CantReadException
     * @throws NotFoundException
     */
    public function getLanguage(ContainerInterface $container)
    {
	    /** @var Config $config */
	    $config = $container->get('config');

        /** @var Config $config */
        $preferences = $container->get('preferences');
        
        $loader = new JSONLoader($container['root'] . DIRECTORY_SEPARATOR . 'languages');
        $provider = new SlimProvider($container->get('request'));
        return new Language($loader, $provider,
	        $preferences->getNode('languages')->toArray(),
	        $preferences->getNode(PreferencesConstants::CATEGORY_MAIN)->get(PreferencesConstants::MAIN_LANGUAGE),
            $config->getNode('debug')->get('lang')
        );
    }
    
    /**
     * @param ContainerInterface $container
     * @return Permissions
     * @throws \GameX\Core\Cache\NotFoundException
     */
    public function getPermissions(ContainerInterface $container)
    {
        return new Permissions($container);
    }
    
    /**
     * @param ContainerInterface $container
     * @return Sentinel
     */
    public function getAuth(ContainerInterface $container)
    {
        $container->get('db');
        $bootsrap = new SentinelBootstrapper($container->get('request'), $container->get('session'));
        return $bootsrap->createSentinel();
    }

    public function getSocial(ContainerInterface $container) {
	    /** @var \Slim\Interfaces\RouterInterface $router */
	    $router = $container->get('router');

	    /** @var \Slim\Http\Uri $request */
	    $uri = $container->get('request')->getUri();
	    $basePath = $uri->getScheme() . '://' . $uri->getAuthority();

	    $callback =new HybridauthCallback($basePath, $router, UserConstants::ROUTE_SOCIAL);
	    $session = new HybridauthSession($container->get('session'));
        $logger = new HybridauthLogger($container->get('log'));

        $social = new SocialAuth($callback, null, $session, $logger);

    	/** @var Config $preferences */
    	$preferences = $container->get('preferences');
    	$socialConfig = $preferences->getNode(PreferencesConstants::CATEGORY_SOCIAL);

    	$value = $socialConfig->getNode('steam');
    	if ($value->get('enabled')) {
    		$social->addProvider(
    			'steam',
			    'Steam',
			    $value->get('icon'),
			    HybridauthSteamProvider::class,
			    ['openid_identifier' => 'http://steamcommunity.com/openid']
		    );
	    }

	    $value = $socialConfig->getNode('vk');
	    if ($value->get('enabled')) {
		    $social->addProvider(
		    	'vk',
			    'Vkontakte',
			    $value->get('icon'),
			    VkontakteProvider::class,
			    [
				    'keys' => [
					    'id' => $value->get('id'),
					    'key' => $value->get('key'),
					    'secret' => $value->get('secret')
				    ]
			    ]
		    );
	    }

	    $value = $socialConfig->getNode('facebook');
	    if ($value->get('enabled')) {
		    $social->addProvider(
		    	'facebook',
			    'Facebook',
			    $value->get('icon'),
			    FacebookProvider::class,
			    [
				    'keys' => [
					    'id' => $value->get('id'),
					    'key' => $value->get('key'),
					    'secret' => $value->get('secret')
				    ]
			    ]
		    );
	    }

	    $value = $socialConfig->getNode('discord');
	    if ($value->get('enabled')) {
		    $social->addProvider(
		    	'discord',
			    'Discord',
			    $value->get('icon'),
			    DiscordProvider::class,
			    [
				    'keys' => [
					    'id' => $value->get('id'),
					    'key' => $value->get('key'),
					    'secret' => $value->get('secret')
				    ]
			    ]
		    );
	    }

	    return $social;
    }
    
    /**
     * @param ContainerInterface $container
     * @return Twig
     * @throws NotFoundException
     */
    public function getView(ContainerInterface $container)
    {
        /** @var Config $config */
        $config = $container->get('config');
        /** @var Config $preferences */
        $preferences = $container->get('preferences');
        
        $settings = $config->getNode('view')->toArray();
        $settings['cache'] = $container->get('root') . 'runtime' . DIRECTORY_SEPARATOR . 'twig_cache';
        $theme = $preferences
            ->getNode(PreferencesConstants::CATEGORY_MAIN)
            ->get(PreferencesConstants::MAIN_THEME, 'default');
        
        $root = $container->get('root') . 'theme' . DIRECTORY_SEPARATOR;
        
        $paths = [
            $root . $theme . DIRECTORY_SEPARATOR . 'templates'
        ];
        
        // Fallback for custom theme haven't needed template
        if ($theme !== 'default') {
            $paths[] = $root . 'default' . DIRECTORY_SEPARATOR . 'templates';
        }
        
        $view = new Twig($paths, $settings);
        
        /** @var \Psr\Http\Message\UriInterface $uri */
        $uri = $container->get('request')->getUri();
        $view->addExtension(new TwigExtension($container->get('router'), $container->get('base_url')));
        $view->addExtension(new TwigVersionExtension($container->get('manifest')));
        $view->addExtension(new CSRFExtension($container->get('csrf')));
        $view->addExtension(new AuthViewExtension($container));
        $view->addExtension(new LangViewExtension($container));
        $view->addExtension(new AccessFlagsViewExtension());
        $view->addExtension(new Twig_Dump());
        $view->addExtension(new UploadFlagsViewExtension($container));
        $view->addExtension(new ConstantsViewExtension());
        
        $view->getEnvironment()->addGlobal('flash_messages', $container->get('flash'));
        $view->getEnvironment()->addGlobal('currentUri', (string)$uri->getPath());
        $view->getEnvironment()->addGlobal('title', $preferences
            ->getNode(PreferencesConstants::CATEGORY_MAIN)
            ->get(PreferencesConstants::MAIN_TITLE)
        );

        $view->getEnvironment()->addGlobal('breadcrumbs', $container->get('breadcrumbs'));

        /** @var \Slim\Http\Uri $uri */
        $uri = $container->get('request')->getUri();
        $view->getEnvironment()->addGlobal('base_uri', $uri->getScheme() . '://' . $uri->getHost() . $uri->getBasePath());
        
        return $view;
    }
    
    /**
     * @param ContainerInterface $container
     * @return MailHelper
     * @throws NotFoundException
     */
    public function getMail(ContainerInterface $container)
    {
        /** @var Config $config */
        $config = $container->get('preferences');
        
        return new SwiftMailer($container->get('view'), $config->getNode('mail'));
    }
    
    /**
     * @param ContainerInterface $container
     * @return Token
     */
    public function getCSRF(ContainerInterface $container)
    {
        return new Token($container->get('session'));
    }
    
    /**
     * @param ContainerInterface $container
     * @return FlashMessages
     */
    public function getFlashMessages(ContainerInterface $container)
    {
        return new FlashMessages($container->get('session'), 'flash_messages');
    }

	/**
	 * @param ContainerInterface $container
	 * @return Upload
	 */
    public function getUpload(ContainerInterface $container)
    {
        return new Upload($container->get('root') . 'uploads', $container->get('base_url') . '/uploads');
    }

	/**
	 * @param ContainerInterface $container
	 * @return Manifest
	 */
    public function getManifest(ContainerInterface $container)
    {
        return new Manifest($container->get('root') . 'manifest.json');
    }

	/**
	 * @param ContainerInterface $container
	 * @return Updater
	 */
    public function getUpdater(ContainerInterface $container)
    {
        return new Updater($container->get('manifest'));
    }

	/**
	 * @return Breadcrumbs
	 */
    public function getBreadcrumbs()
    {
        return new Breadcrumbs();
    }
}
