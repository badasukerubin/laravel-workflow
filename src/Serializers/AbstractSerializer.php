<?php

declare(strict_types=1);

namespace Workflow\Serializers;

use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use Laravel\SerializableClosure\SerializableClosure;
use Throwable;

abstract class AbstractSerializer implements SerializerInterface
{
    use SerializesAndRestoresModelIdentifiers;

    abstract public static function getInstance(): self;

    abstract public static function encode(string $data): string;

    abstract public static function decode(string $data): string;

    public static function serializable($data): bool
    {
        try {
            serialize($data);
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    public static function serializeModels($data)
    {
        if (is_array($data)) {
            $self = static::getInstance();
            foreach ($data as $key => $value) {
                $data[$key] = $self->getSerializedPropertyValue($value);
            }
        } elseif ($data instanceof Throwable) {
            $data = [
                'class' => get_class($data),
                'message' => $data->getMessage(),
                'code' => $data->getCode(),
                'line' => $data->getLine(),
                'file' => $data->getFile(),
                'trace' => collect($data->getTrace())
                    ->filter(static fn ($trace) => static::serializable($trace))
                    ->toArray(),
            ];
        }
        return $data;
    }

    public static function unserializeModels($data)
    {
        if (is_array($data)) {
            $self = static::getInstance();
            foreach ($data as $key => $value) {
                $data[$key] = $self->getRestoredPropertyValue($value);
            }
        }
        return $data;
    }

    public static function serialize($data): string
    {
        SerializableClosure::setSecretKey(config('app.key'));
        $data = static::serializeModels($data);
        return static::encode(serialize(new SerializableClosure(static fn () => $data)));
    }

    public static function unserialize(string $data)
    {
        SerializableClosure::setSecretKey(config('app.key'));
        $unserialized = unserialize(static::decode($data));
        if ($unserialized instanceof SerializableClosure) {
            $unserialized = ($unserialized->getClosure())();
        }
        return static::unserializeModels($unserialized);
    }
}
