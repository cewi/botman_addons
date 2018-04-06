# botman_addons
Some extra stuff for [BotMan](https://botman.io) 

WIP - details may change

## RasaNLU Middleware

BotMan is a great tool for creating chatbots. For natural language processing (NLP or NLU) you can use dialogflow or wit.ai. If you don't want to pass the sentences of your users to google or facebook, [Rasa](https://nlu.rasa.com/) can do a great job. The preferred solution would be to let Rasa mimic the Api of Wit.ai. But in the case that you need or want to use the native api of Rasa, BotmMn doesn't ship with a middleware for that purpose. You've first to set up Rasa's [Http:-Interface](https://nlu.rasa.com/http.html). Then you've to pull in the middleware via composer. Add:
```
 "repositories": [
         {
            "type": "vcs",
            "url": "https://github.com/cewi/botman_addons"
        }
    ],
 ```
 to your composer.json and 
 ```
 "cewi/botman_addons": "dev-master"
 ```
 in the require section. In your controller add the middleware like this:
 ```
use Cewi\BotManAddons\Middleware\RasaNLU;

public function handle()
    {
        $botman = app('botman');
        
        // Apply global "received" middleware
        $rasa = RasaNLU::create();
        $botman->middleware->received($rasa);

        $botman->group(['middleware' => $rasa], function ($botman) {

          $botman->hears('some_intent', function (BotMan $bot) {
            $entities = $bot->getMessage()->getExtras('entities');
            
            do something with the entities...
            
            });
      }
    
 }
```
See [https://botman.io/2.0/middleware](https://botman.io/2.0/middleware) how to apply middleware in BotMan.

