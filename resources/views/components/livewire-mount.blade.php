<?php
    $__lw_mount = app('livewire')->mount($componentClass);
    echo is_object($__lw_mount) && method_exists($__lw_mount, 'html') ? $__lw_mount->html() : $__lw_mount;
?>
