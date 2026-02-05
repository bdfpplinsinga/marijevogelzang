<?php

namespace Burotoast\EventbriteImporter\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class ImportEvents extends Command
{
    protected $signature = 'eventbrite:import';
    protected $description = 'Import events from Eventbrite using organizer ID';

    public function handle()
    {
        $token = config('eventbrite.token');
        $organizerId = config('eventbrite.organizer_id');

        $this->info('Token: ' . config('eventbrite.token'));
        $this->info('Organizer ID: ' . config('eventbrite.organizer_id'));

        if (!$token || !$organizerId) {
            $this->error('Missing Eventbrite config.');
            return;
        }

        $response = Http::withToken($token)
            ->get("https://www.eventbriteapi.com/v3/organizers/{$organizerId}/events/");

        if ($response->failed()) {
            $this->error('Failed to fetch events.');
            return;
        }

        // Dump the response for debugging
        $events = $response->json('events');
        dump($events); // <- Dumps all event data

        foreach ($response->json('events') as $event) {
            $eventbriteId = $event['id'];
            $slug = Str::slug($event['name']['text']);
            $isOnlineEvent = $event['online_event'] ?? false;

            // Fetch full HTML description
            $descriptionResponse = Http::withToken($token)
                ->get("https://www.eventbriteapi.com/v3/events/{$eventbriteId}/description/");

            $fullDescription = '';
            if ($descriptionResponse->ok()) {
                $fullDescription = $descriptionResponse->json('description') ?? '';
            } else {
                $this->warn("⚠️ Could not fetch description for event ID {$eventbriteId}");
            }

            $structuredContentResponse = Http::withToken($token)
                ->get("https://www.eventbriteapi.com/v3/events/{$eventbriteId}/structured_content/");

            $structuredModules = [];
            if ($structuredContentResponse->ok()) {
                $structuredModules = $structuredContentResponse->json('modules') ?? [];
            } else {
                $this->warn("⚠️ Could not fetch structured content for event ID {$eventbriteId}");
            }
        
            // Fetch ticket classes (pricing info)
            $ticketResponse = Http::withToken($token)
                ->get("https://www.eventbriteapi.com/v3/events/{$eventbriteId}/ticket_classes/");

            // Reset the prices array for each event!
            $prices = [];
        
            if ($ticketResponse->ok()) {
                foreach ($ticketResponse->json('ticket_classes') as $ticket) {
                    $name = $ticket['name'];
                    $cost = $ticket['cost']['display'] ?? 'Free';
                    $free = $ticket['free'];
            
                    $prices[] = [
                        'type' => 'ticket',
                        'id' => Str::uuid()->toString(), // Unique ID per replicator block
                        'enabled' => true,
                        'name' => $name,
                        'cost' => $cost,
                        'free' => $free,
                        'fields' => [
                            'name' => $name,
                            'cost' => $cost,
                            'free' => $free,
                        ],
                    ];
                }
            } else {
                $this->warn("⚠️ Could not fetch pricing for event ID {$eventbriteId}");
            }

            
        

            // Try to find an existing entry by eventbrite_id
            $existing = Entry::query()
                ->where('collection', 'events')
                ->where('eventbrite_id', $eventbriteId)
                ->first();
        
            $data = [
                'title' => $event['name']['text'],
                'summary' => $event['summary'] ?? '',
                'description' => $structuredModules,
                'start_date' => $event['start']['local'],
                'end_date' => $event['end']['local'],
                'eventbrite_id' => $eventbriteId,
                'event_url' => $event['url'],
                'event_id' => $event['id'],
                'is_online_event' => $isOnlineEvent, // Add online event check
                'prices' => $prices, // Store prices array in entry
            ];
        
            if ($existing) {
                $originalData = $existing->data()->all();
                $changes = [];
            
                foreach ($data as $key => $value) {
                    if (!array_key_exists($key, $originalData) || $originalData[$key] !== $value) {
                        $changes[$key] = [
                            'old' => $originalData[$key] ?? null,
                            'new' => $value,
                        ];
                    }
                }
            
                if (!empty($changes)) {
                    $existing->data(array_merge($originalData, $data));
                    $existing->save();
            
                    $this->info("✅ Updated: {$event['name']['text']}");
                    // foreach ($changes as $field => $change) {
                    //     $old = is_array($change['old']) ? json_encode($change['old']) : $change['old'];
                    //     $new = is_array($change['new']) ? json_encode($change['new']) : $change['new'];
                    //     $this->line("  - {$field}: \"{$old}\" → \"{$new}\"");
                    // }
                } else {
                    $this->line("🔁 Unchanged: {$event['name']['text']}");
                }
            } else {
                Entry::make()
                    ->collection('events')
                    ->slug($slug)
                    ->data($data)
                    ->save();
                $this->info("➕ Imported: {$event['name']['text']}");
            }
        }
    }
}