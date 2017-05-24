<?php

namespace Weareenvoy\CraftGreenhouse\controllers;

use craft\elements\Entry;
use craft\web\Controller;
use Weareenvoy\CraftGreenhouse\Exception as GreenhousePluginException;
use Weareenvoy\CraftGreenhouse\Plugin;
use yii\base\Module;

/**
 * Class MainController
 *
 * @package Weareenvoy\CraftGreenhouse\controllers
 */
class MainController extends Controller
{
    /**
     * @var bool
     */
    protected $allowAnonymous = true;

    /**
     * @var \Weareenvoy\CraftGreenhouse\services\Greenhouse
     */
    protected $greenhouse;

    /**
     * MainController constructor.
     *
     * @param string $id
     * @param Module $module
     * @param array  $config
     */
    public function __construct($id, Module $module, array $config = [])
    {
        parent::__construct($id, $module, $config);

        $this->greenhouse = Plugin::getInstance()->greenhouse;
    }

    /**
     * @param Entry $entry
     *
     * @return \yii\web\Response
     */
    public function actionIndex(Entry $entry)
    {
        $template = $this->greenhouse->getTemplateFromEntry($entry);
        $careers  = $this->greenhouse->formatJobsForDisplay($this->greenhouse->getJobListing());

        return $this->renderTemplate($template, compact('entry', 'careers'));
    }

    /**
     * @return \yii\web\Response
     */
    public function actionShow()
    {
        $entry    = $this->greenhouse->getCareerDetailEntry();
        $template = $this->greenhouse->getTemplateFromEntry($entry);
        $job      = $this->greenhouse->getJobFromId($this->getJobId());

        return $this->renderTemplate($template, compact('entry', 'job'));
    }

    /**
     * @return \yii\web\Response
     */
    public function actionApply()
    {
        $this->requirePostRequest();
        $job = $this->greenhouse->getJobFromId($this->getJobId(), true);

        $request = \Craft::$app->request;
        try {
            $this->greenhouse->applyToJob($job, $request);

            var_dump('success');die;

            return $this->redirect(\Craft::$app->request->getUrl());
        } catch (GreenhousePluginException $e) {
            var_dump($e);die;
            $session = \Craft::$app->getSession();
            $session->addFlash('applicationErrors', $e->getErrorMessages());

            $url = $request->getSegments();
            array_pop($url);

            return $this->redirect(implode('/', $url) . '#apply');
        }
    }

    /**
     * @param int $segment
     *
     * @return string
     */
    private function getJobId($segment = 1): string
    {
        $segments = \Craft::$app->request->segments;

        return $segments[$segment];
    }
}
