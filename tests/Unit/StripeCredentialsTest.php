<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Support\StripeCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        StripeCredentials::flush();
        parent::tearDown();
    }

    public function test_setting_takes_precedence_over_config(): void
    {
        StripeCredentials::flush();

        config([
            'services.stripe.secret' => 'sk_from_config',
            'services.stripe.key' => 'pk_from_config',
            'services.stripe.webhook_secret' => 'whsec_from_config',
        ]);

        Setting::set('stripe_secret', 'sk_from_setting');
        Setting::set('stripe_key', 'pk_from_setting');
        Setting::set('stripe_webhook_secret', 'whsec_from_setting');

        $this->assertSame('sk_from_setting', StripeCredentials::secret());
        $this->assertSame('pk_from_setting', StripeCredentials::publishableKey());
        $this->assertSame('whsec_from_setting', StripeCredentials::webhookSecret());
    }

    public function test_falls_back_to_config_when_setting_is_absent(): void
    {
        StripeCredentials::flush();

        config([
            'services.stripe.secret' => 'sk_from_config_only',
            'services.stripe.key' => 'pk_from_config_only',
            'services.stripe.webhook_secret' => 'whsec_from_config_only',
        ]);

        $this->assertSame('sk_from_config_only', StripeCredentials::secret());
        $this->assertSame('pk_from_config_only', StripeCredentials::publishableKey());
        $this->assertSame('whsec_from_config_only', StripeCredentials::webhookSecret());
    }

    public function test_memorizes_value_until_flush(): void
    {
        StripeCredentials::flush();

        config(['services.stripe.secret' => 'sk_initial']);
        $this->assertSame('sk_initial', StripeCredentials::secret());

        // Altera config mas não faz flush: continua devolvendo o memorizado
        config(['services.stripe.secret' => 'sk_altered']);
        $this->assertSame('sk_initial', StripeCredentials::secret());

        // Após flush, resolve o novo valor
        StripeCredentials::flush();
        $this->assertSame('sk_altered', StripeCredentials::secret());
    }
}
