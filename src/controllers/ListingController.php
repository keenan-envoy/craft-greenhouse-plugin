<?php

namespace Weareenvoy\CraftGreenhouse\controllers;

use craft\elements\Entry;
use craft\web\Controller;
use Yii;
use yii\base\Module;

class ListingController extends Controller
{
    protected $allowAnonymous = true;

    public function __construct($id, Module $module, array $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(Entry $entry)
    {
        $careers  = $this->getJobListing();
        $template = $this->getTemplateFromEntry($entry);

        return $this->renderTemplate($template, compact('entry', 'careers'));
    }

    /**
     * @param Entry $entry
     *
     * @return string
     */
    private function getTemplateFromEntry(Entry $entry)
    {
        $siteSettings = $entry->getSection()->getSiteSettings();

        return reset($siteSettings)->template;
    }

    /**
     * @return array
     */
    private function getJobListing(): array
    {
        $jobJson = Yii::$container->get('ghJobs')->getJobs(true);
        try {
            $jobs = json_decode($jobJson, true);
            if (null === $jobs) {
                throw new \Exception();
            }
        } catch (\Exception $e) {
            $jobs = [];
        }

        if (empty($jobs)) {
            return [];
        }

        // format for template
        return [
            'total'    => $jobs['meta']['total'],
            'listings' => $this->sortArray(
                $this->groupArrayBy(array_map(function (array $value) {
                    return [
                        'id'          => $value['id'],
                        'internal_id' => $value['internal_job_id'],
                        'title'       => trim($value['title']),
                        'uri'         => $this->asSegment($value['title']),
                        'grouping'    => trim(reset($value['departments'])['name']),
                    ];
                }, $jobs['jobs']), 'grouping')
            ),
        ];
    }

    /**
     * @param array  $arr
     * @param string $key
     *
     * @return array
     */
    private function groupArrayBy(array $arr, string $key): array
    {
        $newArr = [];

        foreach ($arr as $item) {
            $newArr[$item[$key]]['title']  = $item[$key];
            $newArr[$item[$key]]['jobs'][] = $item;
        }

        return $newArr;
    }

    private function sortArray(array $array)
    {
        foreach ($array as &$row) {
            usort($row['jobs'], function ($a, $b) {
                return $a['title'] > $b['title'] ? 1 : -1;
            });
        }

        usort($array, function ($a, $b) {
            return $a['title'] > $b['title'] ? 1 : -1;
        });

        return $array;
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
