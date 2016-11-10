# MSP_CodeMonkey

Code Monkey is a Magento 2 developers tool to type less and do more :) .

With code monkey you can quickly:
- Create Repositories, model triads and Data API
- TODO (we are still under construction)

## Installation

> composer require msp/codemonkey

## Guide

### Create CRUD

Quick and dirty:

> php bin/magento codemonkey:crud:create My_Module Myentity mydatabasetable

In the previous example, CodeMonkey will read `My_Module` configuration and `mydatabasetable` table **from your DB** and will create model triads,
interfaces and `di.xml` entries according to your configuration.

Getters and setters will be automatically provided in your Model files depending on your table columns configuration.

### Under construction...

Features on the way... stay tuned...


