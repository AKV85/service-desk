@extends('layouts.app')

@section('title', 'Dashboard | Service Desk')

@section('content')
<h2>Dashboard</h2>

<h3>Ticket overview</h3>

<div class="stats">
    <div class="stat-card">
        <span class="stat-label">New</span>
        <strong class="stat-value">{{ $counts['new'] }}</strong>
    </div>

    <div class="stat-card">
        <span class="stat-label">In progress</span>
        <strong class="stat-value">{{ $counts['in_progress'] }}</strong>
    </div>

    <div class="stat-card">
        <span class="stat-label">Resolved</span>
        <strong class="stat-value">{{ $counts['resolved'] }}</strong>
    </div>

    <div class="stat-card">
        <span class="stat-label">Closed</span>
        <strong class="stat-value">{{ $counts['closed'] }}</strong>
    </div>
</div>

@if ($unassignedCount !== null)
<p>Unassigned tickets: {{ $unassignedCount }}</p>
@endif

<h3>Recent tickets</h3>

@if ($recentTickets->isEmpty())
<p>No recent tickets.</p>
@else
<ul>
    @foreach ($recentTickets as $ticket)
    <li>
        <a href="{{ route('tickets.show', $ticket) }}">
            {{ $ticket->title }}
        </a>

        <span class="badge badge-status-{{ $ticket->status->value }}">
            {{ str_replace('_', ' ', ucfirst($ticket->status->value)) }}
        </span>

        <span class="badge badge-priority-{{ $ticket->priority->value }}">
            {{ ucfirst($ticket->priority->value) }}
        </span>
    </li>
    @endforeach
</ul>
@endif

@if ($assignedToMe->isNotEmpty())
<h3>Assigned to me</h3>

<ul>
    @foreach ($assignedToMe as $ticket)
    <li>
        <a href="{{ route('tickets.show', $ticket) }}">
            {{ $ticket->title }}
        </a>
    </li>
    @endforeach
</ul>
@endif
@endsection