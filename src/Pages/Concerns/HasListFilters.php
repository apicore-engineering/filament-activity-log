<?php

namespace Noxo\FilamentActivityLog\Pages\Concerns;

use Carbon\Carbon;
use Exception;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;
use Noxo\FilamentActivityLog\Loggers\Loggers;
use Spatie\Activitylog\Models\Activity;

trait HasListFilters
{
    public ?array $filters = [
        'date_range' => null,
        'causer' => null,
        'subject_type' => null,
        'subject_id' => null,
        'event' => null,
    ];

    protected function queryString()
    {
        $queryString = [];
        foreach (array_keys($this->filters) as $filter) {
            $queryString["filters.{$filter}"] = ['except' => null, 'as' => $filter];
        }

        return $queryString;
    }

    public function resetFiltersForm(): void
    {
        $this->form->fill();
    }

    public function hasActiveFilters(): bool
    {
        return count(array_filter($this->filters)) > 0;
    }

    public function fillFilters(): void
    {
        $values = request()->only(
            array_keys($this->filters),
        );

        $this->form->fill(
            collect($values)
                ->filter(fn ($value) => ! empty($value) && $value !== 'null')
                ->toArray()
        );
    }

    public function applyFilters(Builder $query): Builder
    {
        $state = $this->form->getState();

        $causer = with($state['causer'], function ($causer) {
            if (empty($causer) || ! str_contains($causer, ':')) {
                return null;
            }

            [$causer_type, $causer_id] = explode(':', $causer);

            return compact('causer_type', 'causer_id');
        });

        $query
            ->when(
                $date_range = $this->getDateRange($state['date_range'] ?? null),
                fn (Builder $query) => $query->whereBetween('created_at', $date_range)
            )
            ->unless(
                empty($causer),
                fn (Builder $query) => $query->where($causer)
            )
            ->unless(
                empty($state['subject_type']),
                fn (Builder $query) => $query->where('subject_type', $state['subject_type'])
            )
            ->unless(
                empty($state['subject_id']),
                fn (Builder $query) => $query->where('subject_id', $state['subject_id'])
            )
            ->unless(
                empty($state['event']),
                fn (Builder $query) => $query->where('event', $state['event'])
            );

        return $query;
    }

    protected function getDateRange(?string $date_range): ?array
    {
        if (filled($date_range)) {
            try {
                [$from, $to] = explode(' - ', $date_range);
                $from = Carbon::createFromFormat('d/m/Y', $from)->startOfDay();
                $to = Carbon::createFromFormat('d/m/Y', $to)->endOfDay();

                return compact('from', 'to');
            } catch (Exception $e) {
            }
        }

        return null;
    }

    protected function getDateRangeField()
    {
        return DateRangePicker::make('date_range')
            ->useRangeLabels()
            ->alwaysShowCalendar(false)
            ->label(__('filament-activity-log::activities.filters.date'))
            ->placeholder(__('filament-activity-log::activities.filters.date'));
    }

    protected function getCauserField()
    {
        return Select::make('causer')
            ->label(__('filament-activity-log::activities.filters.causer'))
            ->native(false)
            ->allowHtml()
            ->options(function () {
                $causers = Activity::query()
                    ->groupBy('causer_id', 'causer_type')
                    ->get(['causer_id', 'causer_type'])
                    ->map(fn ($activity) => [
                        'value' => "{$activity->causer_type}:{$activity->causer_id}",
                        'label' => '<div>' .
                            (
                                ($src = $activity->causer?->getFilamentAvatarUrl()) ?
                                '<img src="' . $src . '" class="w-5 h-5 rounded-full inline mr-2"/>'
                                : ''
                            ) .
                            $activity->causer?->name .
                            '</div>',
                    ])
                    ->pluck('label', 'value');

                return $causers;
            });
    }

    protected function getSubjectTypeField()
    {
        return Select::make('subject_type')
            ->label(__('filament-activity-log::activities.filters.subject_type'))
            ->allowHtml()
            ->native(false)
            ->options(
                array_column(
                    array_map(fn ($logger) => [
                        'value' => $logger::$model,
                        'label' => $logger::getLabel(),
                    ], Loggers::$loggers),
                    'label',
                    'value',
                )
            );
    }

    protected function getSubjectIDField()
    {
        return TextInput::make('subject_id')
            ->label(__('filament-activity-log::activities.filters.subject_id'))
            ->visible(fn (callable $get) => $get('subject_type'))
            ->numeric();

    }

    protected function getEventField()
    {
        return Select::make('event')
            ->label(__('filament-activity-log::activities.filters.event'))
            ->visible(fn (callable $get) => $get('subject_type'))
            ->native(false)
            ->options(function (callable $get) {
                $events = Activity::query()
                    ->where('subject_type', $get('subject_type'))
                    ->groupBy('event')
                    ->pluck('event')
                    ->map(fn ($event) => [
                        'value' => $event,
                        'label' => __("filament-activity-log::activities.events.{$event}.title"),
                    ])
                    ->pluck('label', 'value');

                return $events;
            });
    }
}
