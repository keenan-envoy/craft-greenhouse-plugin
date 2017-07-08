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
        $messages = [];
        $session = \Craft::$app->getSession();
        if ($session->hasFlash('applicationSuccess')) {
            $messages['success'] = $session->getFlash('applicationSuccess');
        }
        if ($session->hasFlash('applicationErrors')) {
            $messages['errors'] = $session->getFlash('applicationErrors');
        }

        return $this->renderTemplate($template, compact('entry', 'job', 'messages'));
    }

    /**
     * @return \yii\web\Response
     */
    public function actionApply()
    {
        $this->requirePostRequest();
        $job = $this->greenhouse->getJobFromId($this->getJobId(), true);

        $request = \Craft::$app->request;
        $session = \Craft::$app->getSession();
        $referrerUrl = \Craft::$app->request->getReferrer();
        try {
            $this->greenhouse->applyToJob($job, $request);

            $session->setFlash('applicationSuccess', 'Application submitted successfully!');

            return $this->redirect($referrerUrl . '#apply');
        } catch (GreenhousePluginException $e) {
            $session->setFlash('applicationErrors', $e->getErrorMessages());

            return $this->redirect($referrerUrl . '#apply');
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
