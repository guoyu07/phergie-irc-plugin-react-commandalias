<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link https://github.com/phergie/phergie-irc-plugin-react-commandalias for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Irc\Plugin\React\CommandAlias
 */

namespace Phergie\Irc\Plugin\React\CommandAlias;

use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;

/**
 * Plugin for enabling the use of custom aliases for existing bot commands.
 *
 * @category Phergie
 * @package Phergie\Irc\Plugin\React\CommandAlias
 */
class Plugin extends AbstractPlugin
{
    /**
     * Command aliases
     *
     * @var array
     */
    protected $aliases;

    /**
     * Error code for when the "aliases" configuration key does not reference a
     * non-empty array value
     */
    const ERR_ALIASES_INVALID = 1;

    /**
     * Accepts plugin configuration.
     *
     * Supported keys:
     *
     * aliases - associative array where keys are aliases and corresponding
     * values are the existing bot commands those aliases should invoke
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->aliases = $this->getAliases($config);
    }

    /**
     * Extracts command aliases from configuration.
     *
     * @param array $config
     * @return array Command aliases
     * @throws \RuntimeException 'aliases' configuration key is not set or
     *         references an empty or non-array value
     */
    protected function getAliases(array $config)
    {
        if (empty($config['aliases']) || !is_array($config['aliases'])) {
            throw new \RuntimeException(
                '"aliases" configuration key must reference a non-empty array value',
                self::ERR_ALIASES_INVALID
            );
        }
        return $config['aliases'];
    }

    /**
     * Indicates that the plugin monitors events from the Command and
     * CommandHelp plugins for the aliases it is configured with.
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        $events = array();
        foreach ($this->aliases as $alias => $command) {
            $events['command.' . $alias] = $this->getEventCallback(
                $alias,
                $command,
                'command.' . $command
            );
            $events['command.' . $alias . '.help'] = $this->getEventCallback(
                $alias,
                $command,
                'command.' . $command . '.help'
            );
        }
        return $events;
    }

    /**
     * Returns an event callback.
     *
     * @param string $alias
     * @param string $command
     * @param string $eventName
     * @return callable
     */
    protected function getEventCallback($alias, $command, $eventName)
    {
        $self = $this;
        return function(Event $event, Queue $queue) use ($self, $alias, $command, $eventName) {
            $self->forwardEvent($alias, $command, $eventName, $event, $queue);
        };
    }

    /**
     * Forwards events for command aliases to handlers for their corresponding
     * commands.
     *
     * @param string $alias
     * @param string $command
     * @param string $eventName
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function forwardEvent($alias, $command, $eventName, Event $event, Queue $queue)
    {
        $logger = $this->getLogger();
        $emitter = $this->getEventEmitter();
        $listeners = $emitter->listeners($eventName);
        if (!$listeners) {
            $logger->warning('Alias references unknown command', array(
                'alias' => $alias,
                'command' => $command,
            ));
            return;
        }
        $logger->debug('Forwarding event', array(
            'event_name' => $eventName,
            'alias' => $alias,
            'command' => $command,
        ));
        $emitter->emit($eventName, array($event, $queue));
    }
}
