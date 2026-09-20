<?php

use CseLog\Support;
?>
<div class="card empty">
    <h1><?= Support::e($title ?? 'Error') ?></h1>
    <p><?= Support::e($message ?? 'Something went wrong.') ?></p>
    <a class="btn" href="/board">Back to the board</a>
</div>
