### PHP FPL Training Data and Prediction

This project includes a docker-compose file for ease of connecting the database. Bring up the project then shell in to the php service and run files at will. 

Generate training data with `generate-training.php`, generate data to predict with `generate-unknown.php`. 

Then generate an ML model with `train.php` and use the model in predictions with `predict.php`.

The predictions can then be formatted into JSON for [automatic team-selection](https://github.com/Plastonick/linprog-fpl) using `adapt-predictions.php` (pass in -v to show more info).
