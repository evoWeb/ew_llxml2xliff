<?php

declare(strict_types=1);

/*
 * This file is developed by evoWeb.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Evoweb\EwLlxml2xliff\File;

readonly class TransUnit
{
    protected string $source;

    protected string $target;

    public function __construct(
        string|array $data,
        protected string $key,
        protected string $langKey,
        array $LOCAL_LANG
    ) {
        if (is_array($data)) {
            $this->target = $data['target'] ?? '';
            $this->source = $data['source'] ?? $this->target;
        } else {
            $this->target = $data;
            $this->source = $LOCAL_LANG['default'][$key];
        }
    }

    protected function getPreserved(): string
    {
        return str_contains($this->source, chr(10))
            ? ' xml:space="preserve"'
            : '';
    }

    protected function getApproved(): string
    {
        return $this->langKey !== 'default' ? ' approved="yes"' : '';
    }

    protected function getSourceNode(): string
    {
        return empty($this->source)
            ? '<source/>'
            : '<source>' . htmlspecialchars($this->source) . '</source>';
    }

    protected function getTargetNode(): string
    {
        return empty($this->target)
            ? '<target/>'
            : '<target>' . htmlspecialchars($this->target) . '</target>';
    }

    public function __toString(): string
    {
        return '      <trans-unit id="' . $this->key . '" resname="' . $this->key . '"'
            . $this->getPreserved() . $this->getApproved() . '>' . LF
            . '        ' . $this->getSourceNode() . LF
            . ($this->langKey !== 'default' ? '        ' . $this->getTargetNode() . LF : '')
            . '      </trans-unit>';
    }
}
