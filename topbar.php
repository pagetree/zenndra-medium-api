        <header>
            <p class="wordmark"><a href="<?= htmlspecialchars($home, ENT_QUOTES, 'UTF-8') ?>">Zenndra</a></p>
            <div class="meta">
                <a class="ref-link" href="<?= htmlspecialchars(public_url('ref'), ENT_QUOTES, 'UTF-8') ?>"<?= !empty($onRef) ? ' aria-current="page"' : '' ?>><?= $ref ?></a>
                <time id="clock" datetime="">00:00:00</time>
            </div>
        </header>
