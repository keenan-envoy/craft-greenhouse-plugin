<?php

namespace Weareenvoy\CraftGreenhouse\services;

use Craft;
use craft\elements\Entry;
use craft\web\Request;
use DOMDocument;
use Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIResponseException;
use Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseApplicationException;
use Illuminate\Support\Collection;
use Weareenvoy\CraftGreenhouse\Exception as GreenhousePluginException;
use yii\base\Component;

/**
 * Class Greenhouse
 *
 * @package Weareenvoy\CraftGreenhouse\services
 */
class Greenhouse extends Component
{
    /* Used for optional Fields */
    protected $_optional = ["linkedin", "website", "hear_about"];
    
    /**
     * @param Entry $entry
     *
     * @return string
     */
    public function getTemplateFromEntry(Entry $entry): string
    {
        $siteSettings = $entry->getSection()->getSiteSettings();

        return reset($siteSettings)->template;
    }

    /**
     * Get a formatted listing of all Envoy jobs
     *
     * @return array
     */
    public function getJobListing(): array
    {
        $jobJson = Craft::$container->get('ghJobs')->getJobs(true);
        try {
            $jobs = json_decode($jobJson, true);
            if (null === $jobs) {
                return [];
            }

            return $jobs;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @param array $jobs
     *
     * @return array
     */
    public function formatJobsForDisplay(array $jobs): array
    {
        if (empty($jobs)) {
            return [];
        }

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
     * @param string|int $id
     * @param bool       $questions
     *
     * @return array
     * @throws \Exception
     */
    public function getJobFromId($id, bool $questions = false): array
    {
        /** @var \Greenhouse\GreenhouseToolsPhp\Services\JobApiService $jobApi */
        $jobApi = \Craft::$container->get('ghJobs');

        if ( ! is_numeric($id)) {
            $response = json_decode($jobApi->getJobs(true), true);
            $jobs     = $response['jobs'];

            $filtered = array_filter($jobs, function ($job) use ($id) {
                return $id === $this->asSegment($job['title']);
            });

            if (empty($filtered)) {
                throw new \Exception('No jobs found!');
            }

            $id = (int)(array_values($filtered)[0]['id']);
        }

        $job            = json_decode($jobApi->getJob($id, $questions), true);
        $job['content'] = $this->parseJobContent($job['content']);

        return $job;
    }

    /**
     * @return Entry
     */
    public function getCareerDetailEntry(): Entry
    {
        return Entry::find()->section('careerDetail')->one();
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

    /**
     * @param array $array
     *
     * @return array
     */
    private function sortArray(array $array): array
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

    /**
     * all h# tags     ---- section start
     * everything else ---- content item
     *
     * @param string $originalHtml
     *
     * @return array
     */
    private function parseJobContent(string $originalHtml): array
    {
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8"?>' . html_entity_decode($originalHtml), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOXMLDECL);

        // Stupid hack to keep valid HTML but also make it UTF-8
        /** @var \DOMNode $node */
        foreach ($document->childNodes as $node) {
            if (XML_PI_NODE === $node->nodeType) {
                $document->removeChild($node);
                break;
            }
        }
        $document->encoding = 'UTF-8';
        // End Stupid hack.

        $headings       = [];
        $sections       = [];
        $currentSection = null;
        foreach ($document->childNodes as $node) {
            switch ($node->nodeName) {
                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                    $currentSection = $this->asSegment($node->textContent);
                    if ( ! empty($currentSection)) {
                        $headings[$currentSection] = $node->textContent;
                    }
                    break;

                default:
                    if ( ! empty($currentSection)) {
                        $sections[$currentSection][] = $document->saveHTML($node);
                    }
            }
        }

        $sections = array_map(function (array $grouping) {
            return implode("\n", $grouping);
        }, $sections);
        $headings = array_filter($headings, function ($key) use ($sections) {
            return ! empty($sections[$key]);
        }, ARRAY_FILTER_USE_KEY);

        return compact('headings', 'sections');
    }

    /**
     * @param array   $job
     * @param Request $request
     *
     * @return bool
     * @throws GreenhousePluginException
     */
    public function applyToJob(array $job, Request $request)
    {
        /** @var \Greenhouse\GreenhouseToolsPhp\Services\ApplicationService $appService */
        $appService = \Craft::$container->get('ghApps');

        $data = $this->getApplicationData($request);

        try {
            $this->validateApplication($data);

            return $appService->postApplication($this->translateDataForApi($data, $job));
        } catch (GreenhouseApplicationException $e) {
            // Missing required field
            // this should never be hit, unless GH changes their API
            throw new GreenhousePluginException([$e->getMessage()], $e->getMessage(), $e->getCode(), $e);
        } catch (GreenhouseAPIResponseException $e) {
            throw new GreenhousePluginException([$e->getMessage()], $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param Request $request
     *
     * @return Collection
     */
    private function getApplicationData(Request $request): Collection
    {
        return collect([
            'first_name' => $request->getBodyParam('first_name'),
            'last_name'  => $request->getBodyParam('last_name'),
            'email'      => $request->getBodyParam('email'),
            'phone'      => $request->getBodyParam('phone'),
            'resume'     => new \CURLFile($_FILES['resume']['tmp_name'], $_FILES['resume']['type'], 'resume'),
            'linkedin'   => $request->getBodyParam('linkedin'),
            'website'    => $request->getBodyParam('website'),
            'hear_about' => $request->getBodyParam('hear_about'),
        ]);
    }

    /**
     * @param Collection $data
     *
     * @throws GreenhousePluginException
     */
    private function validateApplication(Collection $data)
    {
        $errors = $data->map(function ($value, $key) {
            if (empty($value) && !in_array($key, $this->_optional)) {
                return 'Required.';
            }

            if ('email' === $key && ! $this->isValidEmail($value)) {
                return 'Invalid email address.';
            }

            if ('phone' === $key && ! $this->isValidPhone($value)) {
                return 'Invalid phone number.';
            }

            if ('resume' === $key && ! $this->isValidResume($value)) {
                return 'Invalid resume file.';
            }

            return '';
        })->filter();

        if (0 < $errors->count()) {
            throw new GreenhousePluginException($errors->toArray());
        }
    }

    /**
     * @param string $email
     *
     * @return bool
     */
    private function isValidEmail(string $email): bool
    {
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * @param string $phone
     *
     * @return bool
     */
    private function isValidPhone(string $phone)
    {
        return 10 <= strlen(preg_replace(';\D;', '', $phone));
    }

    private function isValidResume(\CURLFile $resume)
    {
        return in_array($resume->getMimeType(), [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * @param Collection $data
     * @param array      $job
     *
     * @return array
     */
    private function translateDataForApi(Collection $data, array $job): array
    {
        return $data->mapWithKeys(function ($value, $key) use ($job) {
            if ('phone' === $key) {
                $value = substr($value, 0, 3) . ' ' . substr($value, 3, 3) . ' ' . substr($value, 6);
            }

            return [$this->translateKey($key, $job) => $value];
        })->put('id', $job['id'])->toArray();
    }

    /**
     * @param string $key
     * @param array  $job
     *
     * @return string
     */
    private function translateKey(string $key, array $job): string
    {
        $translations = $this->getKeyTranslations();

        if ($translations->has($key)) {
            if (is_callable($translations[$key])) {
                return $translations[$key]($job);
            }

            return $translations[$key];
        }

        return $key;
    }

    /**
     * @return Collection
     */
    private function getKeyTranslations(): Collection
    {
        return collect([
            'first_name' => 'first_name',
            'last_name'  => 'last_name',
            'email'      => 'email',
            'phone'      => 'phone',
            'resume'     => 'resume',
            'linkedin'   => function (array $job) {
                return array_values(array_filter($job['questions'], function ($question) {
                    return 'LinkedIn Profile' === $question['label'];
                }))[0]['fields'][0]['name'];
            },
            'website'    => function (array $job) {
                return array_values(array_filter($job['questions'], function ($question) {
                    return 'Website' === $question['label'];
                }))[0]['fields'][0]['name'];
            },
            'hear_about' => function (array $job) {
                return array_values(array_filter($job['questions'], function ($question) {
                    return 'How did you hear about this job?' === $question['label'];
                }))[0]['fields'][0]['name'];
            },
        ]);
    }
}
