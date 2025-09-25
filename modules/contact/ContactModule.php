<?php
namespace modules\contact;

use Craft;
use yii\base\Module;

class ContactModule extends Module
{
    public function init(): void
    {
        parent::init();

        // Set this module's controller namespace only, do not override the app's
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\contact\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\contact\\controllers';
        }
    }
}


