<?php /* Portfolio shared JS — graph and Gantt utilities loaded on every page so that
         hook-rendered widgets can use them without per-template script tags. */ ?>
<script src="<?= $this->text->e($this->url->href('PortfolioController', 'asset', ['file' => 'Asset/js/portfolio-graph.js', 'plugin' => 'Portfolio'])) ?>"></script>
<script src="<?= $this->text->e($this->url->href('PortfolioController', 'asset', ['file' => 'Asset/js/portfolio-gantt.js', 'plugin' => 'Portfolio'])) ?>"></script>
