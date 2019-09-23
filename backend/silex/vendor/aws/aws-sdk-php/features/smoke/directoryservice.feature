# language: en
@smoke @directoryservice @ds
Feature: AWS Directory Service

  I want to use AWS Directory Service

  Scenario: Making a request
    When I call the "DescribeDirectories" API
    Then the value at "DirectoryDescriptions" should be a list

  Scenario: Handling errors
    When I attempt to call the "CreateDirectory" API with:
    | Name      |   |
    | Password  |   |
    | Size      |   |
    Then I expect the response error code to be "ValidationException"

