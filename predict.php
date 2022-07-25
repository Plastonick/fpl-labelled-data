<?php

include __DIR__ . '/vendor/autoload.php';

use Rubix\ML\Loggers\Screen;
use Rubix\ML\Extractors\ColumnPicker;
use Rubix\ML\Extractors\CSV;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Transformers\NumericStringConverter;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;

use function Rubix\ML\array_transpose;

ini_set('memory_limit', '-1');

$logger = new Screen();

$logger->info('Loading data into memory');

$dataset = fopen('dataset.csv', 'r');
$headers = fgetcsv($dataset);
$selectedColumns = array_filter($headers, fn($el) => !in_array($el, ['id', 'score', 'minutes']));
fclose($dataset);

$extractor = new ColumnPicker(new CSV('unknown.csv', true), $selectedColumns);

$dataset = Unlabeled::fromIterator($extractor)
    ->apply(new NumericStringConverter());

$estimator = PersistentModel::load(new Filesystem('fpl.rbx'));

$logger->info('Making predictions');

$predictions = $estimator->predict($dataset);

$extractor = new ColumnPicker(new CSV('unknown.csv', true), ['id']);

$ids = array_column(iterator_to_array($extractor), 'id');

array_unshift($ids, 'id');
array_unshift($predictions, 'score');

$extractor = new CSV('predictions.csv');

$extractor->export(array_transpose([$ids, $predictions]));

$logger->info('Predictions saved to predictions.csv');
