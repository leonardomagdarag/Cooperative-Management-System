<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CreateModelControllers extends Command
{
    protected $signature = 'make:model-controllers';

    protected $description = 'Create resource controllers for all existing models.';

    public function handle()
    {
        $modelsPath = app_path('Models'); // Adjust if your models are in a different directory
        $modelFiles = File::files($modelsPath);

        foreach ($modelFiles as $file) {
            $modelName = pathinfo($file->getFilename(), PATHINFO_FILENAME);

            // Exclude base Model class if present
            if ($modelName === 'Model') {
                continue;
            }

            $controllerName = $modelName.'Controller';

            $this->call('make:controller', [
                'name' => $controllerName,
                '--resource' => true,
            ]);

            $this->info("Controller '{$controllerName}' created successfully.");
        }

        $this->info('All model controllers created!');
    }
}
