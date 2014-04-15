Middleware
==========

[API Specification](https://wiki.openrightsgroup.org/wiki/Censorship_Monitoring_Project_API) 

Using the Vagrant VM Image
--------------------------

Download and install:

* [Oracle Virtualbox](https://www.virtualbox.org/wiki/Downloads)
* [Vagrant](https://www.vagrantup.com/downloads.html)

Obtain a git checkout of the Blocking Middleware repository, then run:

    cd /path/to/Blocking-Middleware
    vagrant up

This will set up and run the VM image. The initial download of the compressed filesystem image can take a few minutes (size: 300MB)

The resulting VM contains a webserver configured to service requests by running the PHP pages from your checkout.  A ready-configured MySQL database is already running in the VM.

You should then be able to execute API commands against your local running instance by
using the base URL [http://localhost:8080/api/1.2/](http://localhost:8080/api/1.2/)


Get involved!
=============

We welcome new contributors especially - we hope you find getting involved both easy and fun. All you need to get started is a github account.

[More ways to get involved with the wider project](http://www.blocked.org.uk/help).

Quick-start guide
-----------------

Here's the short version of the process for people already familiar with github.

You are welcome to contribute on any [issues labelled `ready`](https://github.com/openrightsgroup/Blocking-Middleware/issues?direction=asc&labels=ready&page=1&sort=created&state=open). 

1. Leave a comment on an issue if you want to work on it. We will add you to our `helpers` team if you're not already a member, assign the issue to you and label it as `in progress`, but you don't have to wait for all this before you get started.
2. Fork the repository and make your changes on a new branch with an appropriate name.
3. Send a pull request with your changes once they're complete. Please [refer to the issue number being closed](https://help.github.com/articles/closing-issues-via-commit-messages), either in your commit message, or in the pull request description.
4. We will merge the pull request and this should close the issue automatically if you've tagged it correctly in step 3.

People who contribute positively will be granted write-access to the middleware team's repositories (if they want) so they can commit directly, manage issues, etc.

We really appreciate your help - thank you!

Becoming a helper and selecting issues
--------------------------------------

Get started by signing up as a helper and picking an issue to work on.

0. [Create a github account](https://github.com/) if you don't have one already.
1. Click this badge to see our list of open issues: [![Stories in Ready](https://badge.waffle.io/openrightsgroup/blocking-middleware.png?label=ready&title=Ready)](https://waffle.io/openrightsgroup/blocking-middleware).
2. Pick an issue to work on from the `Ready` column. The issues that would be the most valuable to close are at the top of the list.
3. Log into waffle.io with your github account by clicking the button in the top-left corner. You will be asked to authorise waffle.io to access your github account then you'll be returned to the issues board.
4. Add a comment to the issue you picked saying you'd like it to be assigned to you. Feel free to include any thoughts, questions or ideas you might have for closing the issue, either in the same comment or subsequent ones.
5. You can start working on the issue straight away - no need to wait for confirmation. See the next section for our issue-closing workflow.

We'll assign the issue to you and move it to the `in progress` column so others can see it's being worked on. If this is the first time we've worked together we'll also add you to our [helpers team](https://github.com/orgs/openrightsgroup/teams/helpers).

Working on issues and submitting pull requests
----------------------------------------------

Now that you have an issue in progress, here's our preferred workflow.

If you need help with any of these steps or you don't understand the terms being used please check out the [github help pages](https://help.github.com/articles/fork-a-repo) or contact anyone on the [middleware team](https://github.com/orgs/openrightsgroup/teams/middleware) for assistance.

1. Fork the repository to your github account by using the `fork` button in the top-right corner of its [homepage](https://github.com/openrightsgroup/Blocking-Middleware).
2. Clone your fork to your development machine.
3. Create a new branch for your changes. Call it something descriptive like "issue-38" or "new logo".
4. Make your changes on your new branch.
5. Test your changes to make sure they really close the issue.
6. Commit your changes to your new branch on your development machine. [Refer to the issue number in your commit message](https://help.github.com/articles/closing-issues-via-commit-messages).
7. Push your new branch to your fork of the repository in your personal github account (created in step 1).
8. Visit the github page for your fork and switch to your issue branch.
9. Create a pull request. Describe your changes in the body of the request. Please also refer to the issue number in the pull request body (see link above).
10. A member of the middleware team will merge your pull request and this will close the issue automatically if you've tagged it correctly. If they need any more information or have questions about your changes they will start a conversation with you on the pull request's thread.

We hope you enjoy contributing. Thanks in advance for your hard work!
