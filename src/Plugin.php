<?php

namespace Weareenvoy\CraftGreenhouse;

use Craft;
use craft\base\Element;
use craft\base\Plugin as BasePlugin;
use craft\base\PluginInterface;
use craft\elements\Entry;
use craft\events\RegisterUrlRulesEvent;
use craft\events\SetElementRouteEvent;
use craft\web\UrlManager;
use Greenhouse\GreenhouseToolsPhp\GreenhouseService;
use Weareenvoy\CraftGreenhouse\models\Settings;
use Weareenvoy\CraftGreenhouse\services\Greenhouse;
use yii\base\Event;
use yii\di\Container;

class Plugin extends BasePlugin implements PluginInterface
{
    public function init()
    {
        parent::init();

        $this->initGreenhouse();
        $this->initRoutes();
        $this->initServices();
    }

    protected function createSettingsModel()
    {
        return new Settings();
    }

    private function initGreenhouse()
    {
        /** @var Settings $settings */
        $settings = $this->getSettings();

        Craft::$container->set('greenhouse', function () use ($settings) {
            return new GreenhouseService([
                'apiKey'     => $settings->apiKey,
                'boardToken' => $settings->boardToken,
            ]);
        });

        Craft::$container->set('ghJobs', function (Container $container) {
            return $container->get('greenhouse')->getJobApiService();
        });

        Craft::$container->set('ghApps', function (Container $container) {
            return $container->get('greenhouse')->getApplicationApiService();
        });

        Craft::$container->set('ghBoard', function (Container $container) {
            return $container->get('greenhouse')->getJobBoardService();
        });

        Craft::$container->set('ghHarvest', function (Container $container) {
            return $container->get('greenhouse')->getHarvestService();
        });
    }

    private function initRoutes()
    {
        /** @var Settings $settings */
        $settings = $this->getSettings();
        $urlBase = $settings->urlBase;

        Event::on(Entry::class, Element::EVENT_SET_ROUTE, function (SetElementRouteEvent $event) use ($urlBase) {
            /** @var Entry $entry */
            $entry = $event->sender;

            if ($urlBase === $entry->slug) {
                $event->route   = ['greenhouse/main/index', compact('entry')];
                $event->handled = true;
            }
        });

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function (RegisterUrlRulesEvent $event) use ($urlBase) {
            $event->rules[$urlBase . '/<career:[^/]+>/apply'] = 'greenhouse/main/apply';
            $event->rules[$urlBase . '/<career:[^/]+>'] = 'greenhouse/main/show';
            //$event->rules[$urlBase . '/career-detail'] = 'greenhouse/show/index';
        });
    }

    private function initServices()
    {
        $this->setComponents([
            'greenhouse' => Greenhouse::class,
        ]);
    }
}
