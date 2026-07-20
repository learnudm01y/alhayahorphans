<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SponsorshipUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sponsorshipId;

    /**
     * Create a new event instance.
     */
    public function __construct($sponsorshipId)
    {
        $this->sponsorshipId = $sponsorshipId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('sponsorships'),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $data = \App\Http\Controllers\Api\SponsorshipSyncController::getSingleEnrichedSponsorship($this->sponsorshipId);
        $data['event'] = 'SponsorshipUpdated';
        return $data;
    }
}
