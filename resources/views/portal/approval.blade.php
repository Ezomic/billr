<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Timesheet approval — {{ $client->name }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

<header class="border-b bg-white">
    <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
        <span class="text-lg font-semibold tracking-tight">Billr</span>
        <span class="text-sm text-gray-500">{{ $client->name }}</span>
    </div>
</header>

<main class="mx-auto max-w-3xl px-4 py-10 space-y-8">

    @if(session('approved'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-800">
            All time entries have been approved. Your freelancer will now be able to generate your invoice.
        </div>
    @endif

    <div>
        <h1 class="text-2xl font-semibold">Review your time entries</h1>
        <p class="mt-1 text-sm text-gray-500">
            Please review the hours below and approve them so we can generate your invoice.
        </p>
    </div>

    @if($projects->isEmpty())
        <div class="rounded-lg border bg-white px-6 py-10 text-center text-sm text-gray-500">
            There are no pending time entries awaiting your approval.
        </div>
    @else
        @foreach($projects as $project)
            <section class="rounded-lg border bg-white overflow-hidden">
                <div class="border-b bg-gray-50 px-5 py-3">
                    <h2 class="font-medium">{{ $project->name }}</h2>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="px-5 py-2 font-medium">Date</th>
                            <th class="px-5 py-2 font-medium">Description</th>
                            <th class="px-5 py-2 font-medium text-right">Hours</th>
                            <th class="px-5 py-2 font-medium text-right">Rate</th>
                            <th class="px-5 py-2 font-medium text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($project->timeEntries as $entry)
                            @php
                                $hours    = round($entry->duration_minutes / 60, 2);
                                $rate     = $entry->hourly_rate ?? $project->hourly_rate ?? 0;
                                $subtotal = round(($entry->duration_minutes / 60) * $rate);
                                $currency = $client->currency ?? 'EUR';
                            @endphp
                            <tr @class(['bg-green-50' => $entry->client_approved])>
                                <td class="px-5 py-3 text-gray-500 whitespace-nowrap">
                                    {{ $entry->started_at->format('d M Y') }}
                                </td>
                                <td class="px-5 py-3">
                                    {{ $entry->description ?? '—' }}
                                    @if($entry->client_approved)
                                        <span class="ml-2 inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Approved</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format($hours, 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-gray-500">
                                    {{ number_format($rate / 100, 2) }} {{ $currency }}/hr
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium">
                                    {{ number_format($subtotal / 100, 2) }} {{ $currency }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach

        @unless(session('approved'))
            @php
                $allApproved = $projects->every(fn ($p) => $p->timeEntries->every(fn ($e) => $e->client_approved));
            @endphp

            @if($allApproved)
                <div class="rounded-lg border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-800">
                    All entries have been approved.
                </div>
            @else
                <form method="POST" action="{{ route('client-portal.approve', $token) }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-md bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                    >
                        Approve all time entries
                    </button>
                </form>
            @endif
        @endunless
    @endif
</main>

</body>
</html>
