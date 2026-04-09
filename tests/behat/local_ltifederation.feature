@local @local_ltifederation
Feature: LTI Federation admin management
  In order to connect to remote Moodle sites that publish LTI tools
  As a site administrator
  I need to add, manage and sync LTI provider connections and register tools

  Background:
    Given I log in as "admin"

  @javascript
  Scenario: Admin can add a provider connection
    Given I navigate to "Plugins > Local plugins > LTI Provider connections" in site administration
    When I click on "Add provider" "link"
    And I set the field "Label" to "Test Provider"
    And I set the field "Provider URL" to "https://provider.example.com"
    And I set the field "Web service token" to "testtokenvalue123"
    And I press "Save changes"
    Then I should see "Provider saved successfully"
    And I should see "Test Provider" in the "generaltable" "table"
    And I should see "https://provider.example.com" in the "generaltable" "table"

  @javascript
  Scenario: Admin can view the tool catalog for a provider
    Given the following "local_ltifederation > providers" exist:
      | label         | providerurl                    | wstoken | autosync |
      | Test Provider | https://provider.example.com   | token1  | 0        |
    And the following "local_ltifederation > catalog_entries" exist:
      | providerid    | name           | coursefullname   | ltiversion | regstate |
      | Test Provider | My LTI Tool    | Introduction 101 | LTI-1p3    | none     |
    When I navigate to the tool catalog for "Test Provider"
    Then I should see "Tool catalog: Test Provider"
    And I should see "My LTI Tool" in the "generaltable" "table"
    And I should see "Introduction 101" in the "generaltable" "table"
    And I should see "Not registered" in the "generaltable" "table"

  @javascript
  Scenario: Admin can sync the catalog which queues a cron task
    Given the following "local_ltifederation > providers" exist:
      | label         | providerurl                    | wstoken | autosync |
      | Test Provider | https://provider.example.com   | token1  | 0        |
    When I navigate to the tool catalog for "Test Provider"
    And I click on "Sync now" "link"
    Then I should see "Catalog sync has been queued and will run on the next cron cycle"

  @javascript
  Scenario: Admin can register a tool from the catalog
    Given the following "local_ltifederation > providers" exist:
      | label         | providerurl                    | wstoken | autosync |
      | Test Provider | https://provider.example.com   | token1  | 0        |
    And the following "local_ltifederation > catalog_entries" exist:
      | providerid    | name           | coursefullname   | ltiversion | regstate | registration_url                                              |
      | Test Provider | My LTI Tool    | Introduction 101 | LTI-1p3    | none     | https://provider.example.com/enrol/lti/register.php?token=t1 |
    When I navigate to the tool catalog for "Test Provider"
    And I click on "Register" "link" in the "My LTI Tool" "table_row"
    Then I should see "1 tool(s) registered successfully" or I should see "Registration failed"

  @javascript
  Scenario: Registration state badge shows correctly for a registered tool
    Given the following "local_ltifederation > providers" exist:
      | label         | providerurl                    | wstoken | autosync |
      | Test Provider | https://provider.example.com   | token1  | 0        |
    And the following "local_ltifederation > catalog_entries" exist:
      | providerid    | name            | coursefullname   | ltiversion | regstate   |
      | Test Provider | Registered Tool | Advanced 202     | LTI-1p3    | registered |
      | Test Provider | Pending Tool    | Basics 101       | LTI-1p3    | pending    |
      | Test Provider | Error Tool      | Failed 303       | LTI-1p3    | error      |
    When I navigate to the tool catalog for "Test Provider"
    Then I should see "Registered" in the "Registered Tool" "table_row"
    And I should see "Pending" in the "Pending Tool" "table_row"
    And I should see "Error" in the "Error Tool" "table_row"

  @javascript
  Scenario: Invalid provider URL shows validation error
    Given I navigate to "Plugins > Local plugins > LTI Provider connections" in site administration
    When I click on "Add provider" "link"
    And I set the field "Label" to "Bad Provider"
    And I set the field "Provider URL" to "not-a-valid-url"
    And I set the field "Web service token" to "sometoken"
    And I press "Save changes"
    Then I should see "You must supply a valid URL"

  @javascript
  Scenario: Removed tool shows Removed badge in tool catalog
    Given the following "local_ltifederation > providers" exist:
      | label         | providerurl                    | wstoken | autosync |
      | Test Provider | https://provider.example.com   | token1  | 0        |
    And the following "local_ltifederation > catalog_entries" exist:
      | providerid    | name          | coursefullname | ltiversion | regstate | remotestatus |
      | Test Provider | Removed Tool  | Old Course 101 | LTI-1p3    | none     | 1            |
    When I navigate to the tool catalog for "Test Provider"
    Then I should see "Removed from provider" in the "Removed Tool" "table_row"
