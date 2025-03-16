<?php

namespace App\Services\NBA\Traits;

use Illuminate\Support\Facades\Log;
use Nesk\Puphpeteer\Puppeteer;

trait BrowserSetupTrait
{
      protected function setupPuppeteer()
      {
            $this->puppeteer = new Puppeteer([
                  'idle_timeout' => 120000,
                  'read_timeout' => 60000,
                  'js_extra' => $this->getPuppeteerConfig()
            ]);
      }

      protected function getPuppeteerConfig()
      {
            return "
            const puppeteer = require('puppeteer-extra');
            const StealthPlugin = require('puppeteer-extra-plugin-stealth');
            puppeteer.use(StealthPlugin());
            instruction.setDefaultResource(puppeteer);
        ";
      }

      protected function initializeBrowser()
      {
            $options = [
                  'headless' => false,
                  'stealth' => true,
                  'args' => [
                        '--no-sandbox',
                        '--disable-setuid-sandbox',
                        '--disable-dev-shm-usage',
                        '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                  ],
            ];

            $this->browser = $this->puppeteer->launch($options);
            $this->page = $this->browser->newPage();
            Log::info('Navegador iniciado com sucesso');
      }
}
