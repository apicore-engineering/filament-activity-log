@if (!empty($value))
    @php
        $fields = $field->table->getFields();
    @endphp

    <div class="w-full overflow-x-scroll border border-gray-200 dark:border-white/5 rounded-lg">
        <x-filament-tables::table>
            <x-slot:header>
                <x-filament-tables::row>
                    @foreach ($fields as $field)
                        <x-filament-tables::header-cell class="!p-2">
                            {{ $field->getLabel() }}
                        </x-filament-tables::header-cell>
                    @endforeach
                </x-filament-tables::row>
            </x-slot:header>


            @foreach ($value as $item)
                <x-filament-tables::row @class(['bg-gray-100/30 dark:bg-gray-900/20' => $loop->even])>
                    @foreach ($fields as $field)
                        <x-filament-tables::cell class="p-2 align-top">
                            {{ $field->display(data_get($item, $field->name)) }}
                        </x-filament-tables::cell>
                    @endforeach
                </x-filament-tables::row>
            @endforeach
        </x-filament-tables::table>
    </div>
@endif
