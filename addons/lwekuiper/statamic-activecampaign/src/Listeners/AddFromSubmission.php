<?php

namespace Lwekuiper\StatamicActivecampaign\Listeners;

use Statamic\Facades\Site;
use Illuminate\Support\Arr;
use Statamic\Facades\Addon;
use Statamic\Forms\Submission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Statamic\Events\SubmissionCreated;
use Lwekuiper\StatamicActivecampaign\Facades\FormConfig;
use Lwekuiper\StatamicActivecampaign\Facades\ActiveCampaign;

class AddFromSubmission
{
    private Collection $data;

    private Collection $config;

    public function __construct()
    {
        $this->data = collect();
        $this->config = collect();
    }

    public function getEmail(): string
    {
        return $this->data->get($this->config->get('email_field', 'email'));
    }

    public function hasFormConfig(Submission $submission): bool
    {
        $edition = Addon::get('lwekuiper/statamic-activecampaign')->edition();

        $site = $edition === 'pro'
            ? Site::findByUrl(URL::previous()) ?? Site::default()
            : Site::default();

        if (! $formConfig = FormConfig::find($submission->form()->handle(), $site->handle())) {
            return false;
        }

        $this->data = collect($submission->data());

        $this->config = collect($formConfig->fileData());

        return true;
    }

    public function hasConsent(): bool
    {
        if (! $field = $this->config->get('consent_field')) {
            return true;
        }

        return filter_var(
            Arr::get(Arr::wrap($this->data->get($field, false)), 0, false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function handle(SubmissionCreated $event): void
    {
        if (! $this->hasFormConfig($event->submission)) {
            return;
        }

        if (! $this->hasConsent()) {
            return;
        }

        if (! $contact = $this->syncContact()) {
            return;
        }

        $contactId = Arr::get($contact, 'contact.id');

        $this->updateListStatuses($contactId);

        if ($this->config->get('tag_id')) {
            $this->addTagToContact($contactId);
        }
    }

    private function syncContact(): ?array
    {
        $email = $this->getEmail();
        $mergeData = $this->getMergeData();

        return ActiveCampaign::syncContact($email, $mergeData);
    }

    private function getMergeData(): array
    {
        $mergeFields = $this->config->get('merge_fields', []);

        [$standardFields, $customFields] = collect($mergeFields)->partition(function ($item) {
            return in_array($item['activecampaign_field'], ['email', 'firstName', 'lastName', 'phone']);
        });

        $standardData = $standardFields->mapWithKeys(function ($item) {
            return [$item['activecampaign_field'] => $this->data->get($item['statamic_field'])];
        })->filter()->all();

        $customData = $customFields->map(function ($item) {
            return [
                'field' => $item['activecampaign_field'],
                'value' => $this->data->get($item['statamic_field'])
            ];
        })->filter()->values()->all();

        return array_merge($standardData, ['fieldValues' => $customData]);
    }

    private function updateListStatus($contactId): void
    {

        $listId = $this->config->get('list_id');
        // $listId ='1';

        ActiveCampaign::updateListStatus($contactId, $listId);
    }

    private function updateListStatuses($contactId): void
    {
        // Field in the form that holds the selected list IDs
       $listField = 'mailing_lists';

        // Get the field data from the form submission
        $listIds = Arr::wrap($this->data->get($listField, []));

        // Loop through each list ID and subscribe
        foreach ($listIds as $listId) {
            if (is_numeric($listId)) {
                ActiveCampaign::updateListStatus($contactId, (int) $listId);
            }
        }

        dump('List statuses updated for contact ID: ' . $contactId);
    }
    
    private function addTagToContact($contactId): void
    {
        $tagId = $this->config->get('tag_id');

        ActiveCampaign::addTagToContact($contactId, $tagId);
    }
}
