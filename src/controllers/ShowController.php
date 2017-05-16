<?php

namespace Weareenvoy\CraftGreenhouse\controllers;

use craft\elements\Entry;
use craft\web\Controller;
use Greenhouse\GreenhouseToolsPhp\Services\JobApiService;

class ShowController extends Controller
{
    protected $allowAnonymous = true;

    public function actionIndex()
    {
        $job = $this->getJobFromId($this->getJobId());
        $entry = $this->getCareerDetailEntry();
        $job['content'] = html_entity_decode($job['content']);

        return $this->renderTemplate($entry->getSection()->getSiteSettings()[1]->template, compact('entry', 'job'));
    }

    /**
     * @param string|int $id
     *
     * @return array
     */
    private function getJobFromId($id): array
    {
        /** @var JobApiService $jobApi */
        $jobApi = \Craft::$container->get('ghJobs');

        if (is_numeric($id)) {
            return json_decode($jobApi->getJob($id), true);
        }

        $response = json_decode($jobApi->getJobs(true), true);
        $jobs = $response['jobs'];

        return array_values(array_filter($jobs, function ($job) use ($id) {
            return $id === $this->asSegment($job['title']);
        }))[0];
    }

    /**
     * @return string
     */
    private function getJobId(): string
    {
        $segments = \Craft::$app->request->segments;
        array_shift($segments);

        return implode('/', $segments);
    }

    /**
     * @return Entry
     */
    private function getCareerDetailEntry(): Entry
    {
        return Entry::find()->section('careerDetail')->one();
    }

    /**
     * @param string $str
     *
     * @return string
     */
    private function asSegment(string $str): string
    {
        return trim(
            strtolower(
                preg_replace([';[^-a-z0-9];i', ';-+;'], ['-', '-'], $str)
            ),
            '-'
        );
    }
}
