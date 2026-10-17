@extends('layouts.app')

@section('title', __('Downloads'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Downloads') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Large exports and background jobs appear here.') }}</p>

    <ul class="mt-4 divide-y rounded-lg border bg-white">
        @forelse ($jobs as $job)
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                <div>
                    <p class="font-medium">{{ $job->label }}</p>
                    <p class="text-slate-500">{{ $job->status }} · {{ $job->progress }}%</p>
                    @if ($job->error)
                        <p class="text-red-700">{{ $job->error }}</p>
                    @endif
                </div>
                @if ($job->status === \App\Models\AppJob::STATUS_DONE && $job->result_path)
                    <a href="{{ route('exports.download', $job) }}" class="btn-primary">{{ __('Download') }}</a>
                @endif
            </li>
        @empty
            <li class="px-4 py-8 text-center text-slate-500">{{ __('No downloads yet.') }}</li>
        @endforelse
    </ul>
@endsection
