{{-- One shared suggestion list per type-ahead column of "Edit in list". --}}
@foreach (\App\Filament\Resources\Transfers\Tables\InlineEditColumns::TYPE_AHEAD as $column)
    <datalist id="{{ \App\Filament\Resources\Transfers\Tables\InlineEditColumns::datalistId($column) }}">
        @foreach ((\App\Filament\Resources\Transfers\Tables\InlineEditColumns::options($column))() as $value => $label)
            <option value="{{ $value }}"></option>
        @endforeach
    </datalist>
@endforeach
