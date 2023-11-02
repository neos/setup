## General concept

see https://github.com/neos/neos-development-collection/issues/4243#issue-1690745236

TODO: should be later inlined when it doesnt change anymore ;)

## Technical Documentation

The Setup Package operates strongly coupled with flow and sometimes at super early boot time.
This documentation should explain the inner workings and why certain approaches where chosen.


### Overview about the packages

#### Previous "situation", before neos/setup@6

```                                                                                         
                                                                           ┌──────────────────────────────────────────────┐  
                                                                           │               neos/neos-setup                │  
                                                              │            │                                              │  
                                                              │            │              requires neos/neos              │  
                                                              │            │ provides additional steps: neosRequirements, │  
   ┌────────────────────────────────────────────┐             │            │          administrator, siteimport           │  
   │                                            │             │            │                                              │  
   │                                            │             │            └──────────────────────────────────────────────┘  
   │               neos/cli-setup               │             │                                    │                         
   │                                            │    totally unrelated -                           │                         
   │          newer package (Neos 7.3)          │      no code sharing                         requires                      
   │   requires neos/neos (lives in neos dev    │             │                                    │                         
   │                collection)                 │             │                                    │                         
   │                                            │             │                                    ▼                         
   │                                            │             │                 ┌─────────────────────────────────────┐      
   └────────────────────────────────────────────┘             │                 │             neos/setup              │      
                                                              │                 │                                     │      
                                                              │                 │         requires neos/flow          │      
                                                              │                 │   flow specific steps (database)    │      
                                                              │                 │                                     │      
                                                              │                 │      gui setup infrastructure       │      
                                                              │                 │  (backend/configuration/frontend)   │      
                                                                                │                                     │      
                                                                                └─────────────────────────────────────┘                                                                                                                               
```

#### Current state with neos/setup@6

```
                                                                                                                             
                                                                       ┌────────────────────────────────────────────────────┐
                                                                       │                 neos/neos-setup v3                 │
                                                                       │                                                    │
                                                                       │                 requires neos/neos                 │
                                                                       │                                                    │
                                                                       │       cli commands for `setup:imagehandler`        │
                                                                       │                                                    │
                                                                       │              health check extensions:              │
                                                    ┌ ─ ─ ─ ─ ─ ─ ─ ─ ▶│                                                    │
                                                                       │      - (neos escr setup status in version v4)      │
                                                    │                  │          - at least one neos user exists           │
                                              neos specific            │               - image handling configured?         │
  ┌──────────────────────────────┐         commands extracted          │                      - site exists                 │
  │                              │                                     │                           ...                      │
  │        neos/cli-setup        │        - setup:imagehandler         │                                                    │
  │                              │                                     └────────────────────────────────────────────────────┘
  │     will be split up and     │─ ─ ─ ─ ┬ ─ ─ ─ ─ ┘                                             │                          
  │          deprecated          │                                                                │                          
  │                              │        │                                                       │                          
  └──────────────────────────────┘                                                                │                          
                                          │                                                       │                          
                                                                                       ┌─requires─┘                          
                                          │                                            │                                     
                               flow specific commands                                  │                                     
                                      extracted                                        │                                     
                                                                                       │                                     
                                      - welcome                                        ▼                                     
                                  - setup:database  ┌────────────────────────────────────────────────────────────────────┐   
                                          │         │                             neos/setup                             │   
                                                    │                                                                    │   
                                          │         │                         requires neos/flow                         │   
                                                    │                                                                    │   
                                          │         │        `flow welcome` deprecated and becomes `flow setup`          │   
                                                    │                                                                    │   
                                          │         │                 cli command `flow setup:database`                  │   
                                                    │                                                                    │   
                                          └ ─ ─ ─ ─▶│    health check infrastructure (backend/configuration/frontend)    │   
                                                    │                                                                    │   
                                                    │                                                                    │   
                                                    │                    flow specific health checks:                    │   
                                                    │                          - db connection                           │   
                                                    │                    - doctrine migration status                     │   
                                                    │                  - php version web and cli match                   │   
                                                    │                                                                    │   
                                                    └────────────────────────────────────────────────────────────────────┘   
```








### Migrating from `Neos.CliSetup`

The previous package shipping `flow welcome` and `flow setup:database` as well as `flow setup:imagehandler` was `Neos.CliSetup`. 

To ease the migration, eg. keep the command identifiers, but ship them with this package, the command controller was copied.

But this bears a problem: without adjustments, it could happen that both packages are installed side by side.
This would mean that the command `flow setup:database` cant be correctly resolved to one unique command controller.

To prevent the `Neos.CliSetup` being installed in the first place, we declare this package as composer replacement.

### Endpoints / Entry points

We decided to keep using the `/setup` endpoint for the web. For the cli setup `Neos.CliSetup` originally used `flow welcome` as entry point, but because it would be cool to have a similar entry point we decided for `flow setup`.

You might be wondering how it was possible for `flow welcome` to function, as you dont have to specify the actually command name: Simple, because the WelcomeController only had one method `indexCommand` (name can be chosen freely).
Flow doesn't offer us to have `flow setup` point directly to our entry point while we also have other commands like `flow setup:database` and more.
To work around this limitation we also use our `COMMAND_IDENTIFIER_ALIAS_MAP` to rewrite the `setup` to `setup:index`.

Another redirect is also setup to redirect any requests from `flow welcome` to `flow setup`, to provide a 1 to 1 replacement for `Neos.CliSetup`.

### Setup CLI Endpoint

We have a custom request handler in place to realize pre compile time / super early, nearly no boot time checks.

This has the advantage
- No errors will be thrown when there are invalid php sources (as we don't attempt to require many classes / and maybe because we dont trigger the reflection service?)
- On Windows, the compiling might not succeed if the php binary is not set correctly
- We dont want to depend on caching framework
- Speed for the initial low level checks
- Because so little of the system is active, little can fail so it should provide a super stable endpoint across web hostings and development machines.


### Consequences of pre compile time

Our web request handler needs to take care itself of routing the JS & CSS files.

But this might not be anticipated by the webserver: Eg it might thing JS and CSS are always to be found in `_Resources`. To avoid this problem, we simply use no extension and request `main_js` instead.

We must use the Configuration manager without cache, as the caching framework and file monitor listener will not be active yet, and otherwise it would lead to a frozen/invalid cache. (In my testing on a fast maschine there is no difference between cache and no cache)
