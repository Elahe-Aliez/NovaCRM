<?php

namespace App\Nova\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

class ExportToCsv extends Action
{
    /**
     * @var string
     */
    public $name = 'Export to Excel';

    /**
     * @var string
     */
    protected string $filename;

    /**
     * @var array<string, string|callable>
     */
    protected array $columns;

    /**
     * @var list<string>
     */
    protected array $with;

    /**
     * @param  array<string, string|callable>  $columns
     * @param  list<string>  $with
     */
    public function __construct(string $filename, array $columns, array $with = [])
    {
        $this->filename = $filename;
        $this->columns = $columns;
        $this->with = $with;
    }

    public function handle(ActionFields $fields, Collection $models): Action|ActionResponse
    {
        if ($this->with !== []) {
            $models->loadMissing($this->with);
        }

        $rows = [];
        $rows[] = array_keys($this->columns);

        foreach ($models as $model) {
            $rows[] = array_map(
                fn ($resolver) => $this->resolveValue($resolver, $model),
                array_values($this->columns)
            );
        }

        $csv = $this->toCsv($rows);
        $path = sprintf('exports/%s-%s.csv', Str::uuid()->toString(), Str::slug($this->filename));

        Storage::disk('public')->put($path, $csv);

        return Action::download(
            route('exports.download', ['path' => $path, 'name' => $this->filename.'.csv']),
            $this->filename.'.csv'
        );
    }

    protected function resolveValue(string|callable $resolver, mixed $model): string
    {
        $value = is_callable($resolver)
            ? $resolver($model)
            : data_get($model, $resolver);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value === null ? '' : (string) $value;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    protected function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
