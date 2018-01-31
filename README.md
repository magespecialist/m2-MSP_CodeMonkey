# MSP_CodeMonkey

Code Monkey is a Magento 2 developers tool to type less and do more :) .

With code monkey you can quickly:
- CRUD: Create Repositories, model triads and Data API
- DDD-CQRS: Create get/save/delete commands approach (see MSI Magento module) 

## Installation

> composer require msp/codemonkey

## Guide

### Create CRUD

Quick and dirty:

> php bin/magento msp:cm:crud My_Module Myentity mydatabasetable

In the previous example, CodeMonkey will read `My_Module` configuration and `mydatabasetable` table **from your DB** and will create model triads,
interfaces and `di.xml` entries according to your configuration.

Getters and setters will be automatically provided in your Model files depending on your table columns configuration.

### Create CQRS for DDD approach

Quick and dirty:

> php bin/magento msp:cm:ddd-cqrs My_Module My_ModuleApi Myentity mydatabasetable

In the previous example, CodeMonkey will read `My_Module` configuration and `mydatabasetable` table **from your DB** and will create model triads,
interfaces and `di.xml` entries according to your configuration.

Getters and setters will be automatically provided in your Model files depending on your table columns configuration.

See Magento 2 MSI project for this approach.




