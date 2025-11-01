<?php

use AIToolkit\AIToolkit\Console\AIChatCommand;

it('has the correct command signature', function () {
    $command = new AIChatCommand;

    expect($command->getName())->toBe('ai:chat');
    expect($command->getDescription())->toBe('Chat with AI providers (OpenAI, Anthropic, Groq)');
});

it('accepts prompt argument', function () {
    $command = new AIChatCommand;

    expect($command->getDefinition()->hasArgument('prompt'))->toBeTrue();
    expect($command->getDefinition()->getArgument('prompt')->isRequired())->toBeTrue();
});

it('has provider option', function () {
    $command = new AIChatCommand;

    expect($command->getDefinition()->hasOption('provider'))->toBeTrue();
});

it('has model option', function () {
    $command = new AIChatCommand;

    expect($command->getDefinition()->hasOption('model'))->toBeTrue();
});

it('has max-tokens option', function () {
    $command = new AIChatCommand;

    expect($command->getDefinition()->hasOption('max-tokens'))->toBeTrue();
});

it('has temperature option', function () {
    $command = new AIChatCommand;

    expect($command->getDefinition()->hasOption('temperature'))->toBeTrue();
});

it('has stream option', function () {
    $command = new AIChatCommand;

    expect($command->getDefinition()->hasOption('stream'))->toBeTrue();
});

it('has json option', function () {
    $command = new AIChatCommand;

    expect($command->getDefinition()->hasOption('json'))->toBeTrue();
});
