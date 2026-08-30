@extends('layouts.app')

@section('title', 'Dashboard | Service Desk')

@section('content')
<h2>Dashboard</h2>

<h3>Ticket overview</h3>

<ul>
    <li>New: {{ $counts['new'] }}</li>
    <li>In progress: {{ $counts['in_progress'] }}</li>
    <li>Resolved: {{ $counts['resolved'] }}</li>
    <li>Closed: {{ $counts['closed'] }}</li>
</ul>

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

        — {{ $ticket->status->value }}
        — {{ $ticket->priority->value }}
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