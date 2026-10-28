<?php

final class GuardProbe
{
    public function value()
    {
        if (
            'refs/' . $ref !== ($data['ref'] ?? null)
            || !self::sha($data['object']['sha'] ?? null)
            || !is_string($data['object']['type'] ?? null)
        ) {
            return null;
        }
    }
}
