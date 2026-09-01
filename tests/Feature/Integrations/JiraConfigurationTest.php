<?php

namespace Tests\Feature\Integrations;

use Tests\TestCase;

class JiraConfigurationTest extends TestCase
{
    public function test_jira_integration_configuration_is_available(): void
    {
        $this->assertFalse(config('integrations.jira.enabled'));

        $this->assertArrayHasKey('base_url', config('integrations.jira'));
        $this->assertArrayHasKey('email', config('integrations.jira'));
        $this->assertArrayHasKey('api_token', config('integrations.jira'));
        $this->assertArrayHasKey('project_key', config('integrations.jira'));
        $this->assertArrayHasKey('issue_type_id', config('integrations.jira'));
    }
}
