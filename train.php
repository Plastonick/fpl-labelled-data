<?php

use Rubix\ML\Loggers\Screen;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Extractors\CSV;
use Rubix\ML\Extractors\ColumnPicker;
use Rubix\ML\PersistentModel;
use Rubix\ML\Transformers\NumericStringConverter;
use Rubix\ML\Transformers\MissingDataImputer;
use Rubix\ML\Regressors\GradientBoost;
use Rubix\ML\Regressors\RegressionTree;
use Rubix\ML\Persisters\Filesystem;

require_once __DIR__ . '/vendor/autoload.php';

ini_set('memory_limit', -1);

$logger = new Screen();

$logger->info('Loading data into memory');

$dataset = fopen('dataset.csv', 'r');
$headers = fgetcsv($dataset);
$selectedColumns = array_filter($headers, fn($el) => !in_array($el, ['id', 'minutes']));
fclose($dataset);

$extractor = new ColumnPicker(new CSV('dataset.csv', true), $selectedColumns);

$dataset = Labeled::fromIterator($extractor);

$dataset->apply(new NumericStringConverter())
    ->apply(new MissingDataImputer())
    ->transformLabels('intval');


$estimator = new PersistentModel(
    new GradientBoost(new RegressionTree(4), 0.1),
    new Filesystem('fpl.rbx', true)
);

$estimator->setLogger($logger);

$estimator->train($dataset);

$extractor = new CSV('progress.csv', true);

$extractor->export($estimator->steps());

$logger->info('Progress saved to progress.csv');

if (strtolower(readline('Save this model? (y|[n]): ')) === 'y') {
    $estimator->save();
}



