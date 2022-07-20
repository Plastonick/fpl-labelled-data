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

$extractor = new ColumnPicker(new CSV('dataset.csv', true), [
    'was_home_sub_0','home_difficulty_sub_0','away_difficulty_sub_0','position_id_sub_0','total_points_sub_1','minutes_sub_1','was_home_sub_1','home_difficulty_sub_1','away_difficulty_sub_1','position_id_sub_1','total_points_sub_2','minutes_sub_2','was_home_sub_2','home_difficulty_sub_2','away_difficulty_sub_2','position_id_sub_2','total_points_sub_3','minutes_sub_3','was_home_sub_3','home_difficulty_sub_3','away_difficulty_sub_3','position_id_sub_3','total_points_sub_4','minutes_sub_4','was_home_sub_4','home_difficulty_sub_4','away_difficulty_sub_4','position_id_sub_4','total_points_sub_5','minutes_sub_5','was_home_sub_5','home_difficulty_sub_5','away_difficulty_sub_5','position_id_sub_5','total_points_sub_6','minutes_sub_6','was_home_sub_6','home_difficulty_sub_6','away_difficulty_sub_6','position_id_sub_6','total_points_sub_7','minutes_sub_7','was_home_sub_7','home_difficulty_sub_7','away_difficulty_sub_7','position_id_sub_7','total_points_sub_8','minutes_sub_8','was_home_sub_8','home_difficulty_sub_8','away_difficulty_sub_8','position_id_sub_8','total_points_sub_9','minutes_sub_9','was_home_sub_9','home_difficulty_sub_9','away_difficulty_sub_9','position_id_sub_9','score'
]);

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



