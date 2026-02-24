<?php

namespace Platform\Core\Contracts;

use Illuminate\Database\Eloquent\Model;

interface InheritsExtraFields
{
    /**
     * Parent-Models von denen Extra-Field-Definitionen geerbt werden.
     *
     * @return array<Model> Geladene Eloquent-Instanzen mit HasExtraFields
     */
    public function extraFieldParents(): array;
}
