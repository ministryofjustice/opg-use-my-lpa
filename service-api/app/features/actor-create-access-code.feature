@actor @createAccessCode
Feature: The user is able to create access codes for organisations
  As a user
  I want to be able to create access codes to enable organisations to view my LPA
  So that I do not have to give them the paper LPA

  Background:
    Given I am a user of the lpa application
    And I am currently signed in
    And I have added an LPA to my account

  @integration @acceptance
  Scenario: As a user I can generate an access code for an organisation
    Given I am on the dashboard page
    When I request to give an organisation access to one of my LPAs
    Then I am given a unique access code