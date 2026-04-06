<?php

use App\Enums\MeetingResult;
use App\Enums\MeetingType;
use App\Enums\PipelineStage;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\SalespersonCreatedMeetingNotification;
use App\Notifications\TeamLeaderAssignedMeetingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('notifies the team leader when a salesperson creates a meeting', function () {
    Notification::fake();

    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create(['manager_id' => $manager->id]);
    $salesperson = User::factory()->salesperson()->create(['manager_id' => $teamLeader->id]);
    $client = Client::factory()->create(['owner_id' => $salesperson->id]);

    $meeting = Meeting::factory()->create([
        'client_id' => $client->id,
        'user_id' => $salesperson->id,
        'created_by_id' => $salesperson->id,
    ]);

    Notification::assertSentTo(
        $teamLeader,
        SalespersonCreatedMeetingNotification::class,
        function (SalespersonCreatedMeetingNotification $notification) use ($meeting): bool {
            return $notification->meeting->is($meeting);
        }
    );

    Notification::assertNotSentTo($manager, SalespersonCreatedMeetingNotification::class);
});

it('notifies a salesperson when their team leader assigns a meeting', function () {
    Notification::fake();

    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create(['manager_id' => $manager->id]);
    $salesperson = User::factory()->salesperson()->create(['manager_id' => $teamLeader->id]);
    $client = Client::factory()->create(['owner_id' => $salesperson->id]);

    $meeting = Meeting::factory()->create([
        'client_id' => $client->id,
        'user_id' => $salesperson->id,
        'created_by_id' => $teamLeader->id,
    ]);

    Notification::assertSentTo(
        $salesperson,
        TeamLeaderAssignedMeetingNotification::class,
        function (TeamLeaderAssignedMeetingNotification $notification) use ($meeting): bool {
            return $notification->meeting->is($meeting);
        }
    );

    Notification::assertNotSentTo($teamLeader, TeamLeaderAssignedMeetingNotification::class);
});

it('allows a salesperson to access manager, team leader, and own clients', function () {
    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create(['manager_id' => $manager->id]);
    $salesperson = User::factory()->salesperson()->create(['manager_id' => $teamLeader->id]);
    $ownClient = Client::factory()->create(['owner_id' => $salesperson->id]);
    $teamLeadClient = Client::factory()->create(['owner_id' => $teamLeader->id]);
    $managerClient = Client::factory()->create(['owner_id' => $manager->id]);

    $otherSalesperson = User::factory()->salesperson()->create();
    $otherClient = Client::factory()->create(['owner_id' => $otherSalesperson->id]);

    expect($salesperson->canAccessClient($ownClient))->toBeTrue();
    expect($salesperson->canAccessClient($teamLeadClient))->toBeTrue();
    expect($salesperson->canAccessClient($managerClient))->toBeTrue();
    expect($salesperson->canAccessClient($otherClient))->toBeFalse();
    expect($salesperson->visibleDataOwnerIds())
        ->toContain($salesperson->id)
        ->toContain($teamLeader->id)
        ->toContain($manager->id)
        ->not->toContain($otherSalesperson->id);
});

it('allows a manager to access all clients', function () {
    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create(['manager_id' => $manager->id]);
    $teamSalesperson = User::factory()->salesperson()->create(['manager_id' => $teamLeader->id]);
    $outsideSalesperson = User::factory()->salesperson()->create();

    $managerClient = Client::factory()->create(['owner_id' => $manager->id]);
    $teamLeaderClient = Client::factory()->create(['owner_id' => $teamLeader->id]);
    $teamClient = Client::factory()->create(['owner_id' => $teamSalesperson->id]);
    $outsideClient = Client::factory()->create(['owner_id' => $outsideSalesperson->id]);

    expect($manager->canAccessClient($managerClient))->toBeTrue();
    expect($manager->canAccessClient($teamLeaderClient))->toBeTrue();
    expect($manager->canAccessClient($teamClient))->toBeTrue();
    expect($manager->canAccessClient($outsideClient))->toBeTrue();
    expect($manager->visibleDataOwnerIds())
        ->toContain($manager->id)
        ->toContain($teamLeader->id)
        ->toContain($teamSalesperson->id)
        ->toContain($outsideSalesperson->id);
});

it('allows a team leader to access manager, own, and assigned salesperson clients only', function () {
    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create(['manager_id' => $manager->id]);
    $assignedSalesperson = User::factory()->salesperson()->create(['manager_id' => $teamLeader->id]);
    $otherSalesperson = User::factory()->salesperson()->create();

    $managerClient = Client::factory()->create(['owner_id' => $manager->id]);
    $teamLeaderClient = Client::factory()->create(['owner_id' => $teamLeader->id]);
    $assignedClient = Client::factory()->create(['owner_id' => $assignedSalesperson->id]);
    $otherClient = Client::factory()->create(['owner_id' => $otherSalesperson->id]);

    expect($teamLeader->canAccessClient($managerClient))->toBeTrue();
    expect($teamLeader->canAccessClient($teamLeaderClient))->toBeTrue();
    expect($teamLeader->canAccessClient($assignedClient))->toBeTrue();
    expect($teamLeader->canAccessClient($otherClient))->toBeFalse();
    expect($teamLeader->visibleDataOwnerIds())
        ->toContain($manager->id)
        ->toContain($teamLeader->id)
        ->toContain($assignedSalesperson->id)
        ->not->toContain($otherSalesperson->id);
});

it('stores full meeting history per client with related contact details', function () {
    $salesperson = User::factory()->salesperson()->create();
    $client = Client::factory()->create([
        'owner_id' => $salesperson->id,
        'pipeline_stage' => PipelineStage::Negotiation->value,
    ]);

    $contact = Contact::factory()->create([
        'client_id' => $client->id,
        'created_by_id' => $salesperson->id,
        'name' => 'John Contact',
        'position' => 'CEO',
        'phone' => '+355691234567',
    ]);

    $meeting = Meeting::factory()->create([
        'client_id' => $client->id,
        'contact_id' => $contact->id,
        'user_id' => $salesperson->id,
        'created_by_id' => $salesperson->id,
        'meeting_type' => MeetingType::OnlineMeeting->value,
        'result' => MeetingResult::InProgress->value,
        'purpose' => 'follow-up',
        'scheduled_at' => now()->addDay(),
        'comments' => 'Shume interes i larte nga klienti.',
    ]);

    expect($client->contacts)->toHaveCount(1);
    expect($client->meetings)->toHaveCount(1);
    expect($client->meetings->first()->is($meeting))->toBeTrue();
    expect($meeting->contact?->phone)->toBe('+355691234567');
    expect($contact->creator?->is($salesperson))->toBeTrue();
    expect($meeting->meeting_type)->toBe(MeetingType::OnlineMeeting);
    expect($meeting->result)->toBe(MeetingResult::InProgress);
    expect($meeting->creator?->is($salesperson))->toBeTrue();
});

it('allows salesperson access to meetings assigned by team lead', function () {
    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create(['manager_id' => $manager->id]);
    $salesperson = User::factory()->salesperson()->create(['manager_id' => $teamLeader->id]);

    $teamLeaderClient = Client::factory()->create(['owner_id' => $teamLeader->id]);

    $meeting = Meeting::factory()->create([
        'client_id' => $teamLeaderClient->id,
        'user_id' => $salesperson->id,
        'created_by_id' => $teamLeader->id,
    ]);

    expect($salesperson->canAccessMeeting($meeting))->toBeTrue();
    expect($meeting->creator?->is($teamLeader))->toBeTrue();
});

it('applies user directory visibility by hierarchy', function () {
    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create(['manager_id' => $manager->id]);
    $assignedSalesperson = User::factory()->salesperson()->create(['manager_id' => $teamLeader->id]);
    $otherSalesperson = User::factory()->salesperson()->create();

    expect($manager->visibleUserDirectoryIds())
        ->toContain($manager->id)
        ->toContain($teamLeader->id)
        ->toContain($assignedSalesperson->id)
        ->toContain($otherSalesperson->id);

    expect($teamLeader->visibleUserDirectoryIds())
        ->toContain($teamLeader->id)
        ->toContain($assignedSalesperson->id)
        ->toContain($manager->id)
        ->not->toContain($otherSalesperson->id);

    expect($assignedSalesperson->visibleUserDirectoryIds())
        ->toContain($assignedSalesperson->id)
        ->toContain($otherSalesperson->id)
        ->not->toContain($manager->id)
        ->not->toContain($teamLeader->id);
});
