<?php

namespace App\Filament\Resources\CommunityMessages\Tables;

use App\Filament\Resources\CommunityMessages\Schemas\CommunityMessageInfolist;
use App\Models\CommunityDirectory;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommunityMessagesTable
{
    public static function configure(Table $table): Table
    {
        $communities = collect(CommunityDirectory::all())
            ->mapWithKeys(fn (array $community) => [$community['slug'] => CommunityDirectory::name($community, 'zh_TW')])
            ->all();

        return $table
            ->columns([
                TextColumn::make('member.name')
                    ->label('寄件會員')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'member',
                        fn (Builder $query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"),
                    )),
                TextColumn::make('community_name')->label('社群'),
                TextColumn::make('subject')->label('主旨')->limit(40)->searchable(),
                TextColumn::make('body')
                    ->label('內容')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('狀態')
                    ->formatStateUsing(fn (string $state): string => CommunityMessageInfolist::statuses()[$state] ?? $state)
                    ->badge(),
                TextColumn::make('created_at')->label('送出時間')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('狀態')->options(CommunityMessageInfolist::statuses()),
                SelectFilter::make('community_slug')->label('社群')->options($communities),
                Filter::make('created_at')
                    ->label('送出日期')
                    ->schema([
                        DatePicker::make('from')->label('從'),
                        DatePicker::make('until')->label('到'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
