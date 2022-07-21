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

$extractor = new ColumnPicker(new CSV('unknown.csv', true), [
    'was_home_sub_0','home_difficulty_sub_0','away_difficulty_sub_0','position_id_sub_0','total_points_sub_1','minutes_sub_1','was_home_sub_1','home_difficulty_sub_1','away_difficulty_sub_1','position_id_sub_1','total_points_sub_2','minutes_sub_2','was_home_sub_2','home_difficulty_sub_2','away_difficulty_sub_2','position_id_sub_2','total_points_sub_3','minutes_sub_3','was_home_sub_3','home_difficulty_sub_3','away_difficulty_sub_3','position_id_sub_3','total_points_sub_4','minutes_sub_4','was_home_sub_4','home_difficulty_sub_4','away_difficulty_sub_4','position_id_sub_4','total_points_sub_5','minutes_sub_5','was_home_sub_5','home_difficulty_sub_5','away_difficulty_sub_5','position_id_sub_5','total_points_sub_6','minutes_sub_6','was_home_sub_6','home_difficulty_sub_6','away_difficulty_sub_6','position_id_sub_6','total_points_sub_7','minutes_sub_7','was_home_sub_7','home_difficulty_sub_7','away_difficulty_sub_7','position_id_sub_7','total_points_sub_8','minutes_sub_8','was_home_sub_8','home_difficulty_sub_8','away_difficulty_sub_8','position_id_sub_8','total_points_sub_9','minutes_sub_9','was_home_sub_9','home_difficulty_sub_9','away_difficulty_sub_9','position_id_sub_9',
]);

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
