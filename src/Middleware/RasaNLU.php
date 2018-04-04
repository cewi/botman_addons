<?php

namespace Cewi\BotManAddons\Middleware;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\Http\Curl;
use BotMan\BotMan\Interfaces\HttpInterface;
use BotMan\BotMan\Interfaces\Middleware\Heard;
use BotMan\BotMan\Interfaces\Middleware\Sending;
use BotMan\BotMan\Interfaces\Middleware\Captured;
use BotMan\BotMan\Interfaces\Middleware\Matching;
use BotMan\BotMan\Interfaces\Middleware\Received;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use Illuminate\Support\Collection;

/**
 * RasaNLU Middleware
 * 
 * processes received messages
 * returns intents and entities
 * 
 * Ideas borrowed from Botman's wit.ai middleware https://github.com/botman/botman/blob/2.0/src/Middleware/Wit.php
 *
 */
class RasaNLU implements Received, Captured, Matching, Heard, Sending {

    /** @var string token */
    protected $token = null;

    /** @var HttpInterface */
    protected $http;

    /** @var stdClass */
    protected $response;

    /** @var string */
    protected $lastResponseHash;

    /** @var string */
    protected $rasaUrl = 'http://localhost:5000/parse';

    /** @var bool */
    protected $listenForAction = false;

    /** @var int */
    protected $minimumConfidence = 0.5;

    /** @var string */
    protected $project = 'default';

    /**
     * Rasa constructor
     *
     * @param HttpInterface $http
     * @param string $project Rasa Project, see https://nlu.rasa.com/config.html#project
     * @param minimumConfidence $minimumConfidence
     * 
     * @return void
     */
    public function __construct($token, $minimumConfidence, HttpInterface $http, $project) {
        $this->token = null;
        $this->http = $http;
        $this->project = $project;
        $this->minimumConfidence = $minimumConfidence;
    }

    /**
     * Create a new Rasa middleware instance.
     *
     * @return Rasa
     */
    public static function create($token = null, $minimumConfidence = 0.5, $project = 'default') {
        return new static($token, $minimumConfidence, new Curl(), $project);
    }

    /**
     * Restrict the middleware to only listen for Rasa actions.
     *
     * @param  bool $listen
     * @return $this
     */
    public function listenForAction($listen = true) {
        $this->listenForAction = $listen;
        return $this;
    }

    /**
     * Perform the Rasa API call and cache it for the message.
     *
     * @param  \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
     * @return stdClass
     */
    protected function getResponse(IncomingMessage $message) {
        $response = $this->http->post($this->rasaUrl, [], [
            'query' => [$message->getText()],
            'sessionId' => md5($message->getRecipient()),
            'project' => $this->project,
                ], [
            'Content-Type: application/json; charset=utf-8',
                ], true);

        return $this->response;
    }

    /**
     * Handle a captured message.
     *
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
     * @param BotMan $bot
     * @param $next
     *
     * @return mixed
     */
    public function captured(IncomingMessage $message, $next, BotMan $bot) {
        return $next($message);
    }

    /**
     * Handle an incoming message.
     *
     * @param IncomingMessage $message
     * @param BotMan $bot
     * @param $next
     *
     * @return mixed
     */
    public function received(IncomingMessage $message, $next, BotMan $bot) {
        
        $response = $this->getResponse($message);
        
        $responseData = Collection::make(json_decode($response->getContent(), true));
        $message->addExtras('entities', $responseData->get('entities'));

        return $next($message);
    }

    /**
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
     * @param string $pattern
     * @param bool $regexMatched Indicator if the regular expression was matched too
     * @return bool
     */
    public function matching(IncomingMessage $message, $pattern, $regexMatched) {
        
       $entities = Collection::make($message->getExtras())->get('entities', []);
        if (! empty($entities)) {
            foreach ($entities as $name => $entity) {
                if ($name === 'intent') {
                    foreach ($entity as $item) {
                        if ($item['value'] === $pattern && $item['confidence'] >= $this->minimumConfidence) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Handle a message that was successfully heard, but not processed yet.
     *
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
     * @param BotMan $bot
     * @param $next
     *
     * @return mixed
     */
    public function heard(IncomingMessage $message, $next, BotMan $bot) {
        return $next($message);
    }

    /**
     * Handle an outgoing message payload before/after it
     * hits the message service.
     *
     * @param mixed $payload
     * @param BotMan $bot
     * @param $next
     *
     * @return mixed
     */
    public function sending($payload, $next, BotMan $bot) {
        return $next($payload);
    }

}
