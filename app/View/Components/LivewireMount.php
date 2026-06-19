<?php

namespace App\View\Components;

use Illuminate\View\Component;

class LivewireMount extends Component
{
    public string $componentClass;

    public function __construct(string $componentClass)
    {
        $this->componentClass = $componentClass;
    }

    public function render()
    {
        return view('components.livewire-mount');
    }
}
