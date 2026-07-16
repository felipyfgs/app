# Baseline quality 2026-07-15T22:23:34-03:00

## Ambiente
- node: v24.18.0
- pnpm: 11.9.0
- cwd: /home/obsidian/dev/app/frontend

### lint
```
$ pnpm lint
  50:72  error  'trailing-icon' should be on a new line                                           vue/max-attributes-per-line
  56:64  error  ':title' should be on a new line                                                  vue/max-attributes-per-line

/home/obsidian/dev/app/frontend/app/pages/admin/departments.vue
  84:80  error  '@click' should be on a new line  vue/max-attributes-per-line

/home/obsidian/dev/app/frontend/app/pages/work/index.vue
  293:77  error  '@click' should be on a new line  vue/max-attributes-per-line
  321:71  error  '@click' should be on a new line  vue/max-attributes-per-line

/home/obsidian/dev/app/frontend/app/pages/work/processes/[id].vue
   38:84  error  'label' should be on a new line   vue/max-attributes-per-line
  109:72  error  '@click' should be on a new line  vue/max-attributes-per-line

/home/obsidian/dev/app/frontend/app/pages/work/processes/index.vue
  88:76  error  'class' should be on a new line  vue/max-attributes-per-line

/home/obsidian/dev/app/frontend/app/pages/work/templates/index.vue
  177:82  error  '@click' should be on a new line  vue/max-attributes-per-line

/home/obsidian/dev/app/frontend/app/types/work.ts
   6:25  error  '=' should be placed at the beginning of the line  @stylistic/operator-linebreak
  11:25  error  '=' should be placed at the beginning of the line  @stylistic/operator-linebreak

/home/obsidian/dev/app/frontend/app/utils/navigation.ts
  138:6  error  Unexpected trailing comma                       @stylistic/comma-dangle
  170:1  error  Expected indentation of 12 spaces but found 14  @stylistic/indent
  171:1  error  Expected indentation of 12 spaces but found 14  @stylistic/indent
  172:1  error  Expected indentation of 12 spaces but found 14  @stylistic/indent
  173:1  error  Expected indentation of 12 spaces but found 14  @stylistic/indent
  174:1  error  Expected indentation of 10 spaces but found 12  @stylistic/indent
  272:6  error  Unexpected trailing comma                       @stylistic/comma-dangle

/home/obsidian/dev/app/frontend/tests/e2e/work-module.spec.ts
  104:56  error  Inconsistently quoted property 'Accept' found  @stylistic/quote-props

✖ 21 problems (21 errors, 0 warnings)
  19 errors and 0 warnings potentially fixable with the `--fix` option.

[ELIFECYCLE] Command failed with exit code 1.
exit_code=1
```

### typecheck
```
$ pnpm typecheck
$ nuxt typecheck

 ERROR  EACCES: permission denied, open '/home/obsidian/dev/app/frontend/.nuxt/eslint.config.mjs'

    at async open (node:internal/fs/promises:640:25)
    at async Object.writeFile (node:internal/fs/promises:1260:14)
    at async writeConfigFile (node_modules/.pnpm/@nuxt+eslint@1.16.0_@typescript-eslint+utils@8.63.0_eslint@10.7.0_jiti@2.7.0__typescrip_604ac62b52ff7976c76bdfa3480d1261/node_modules/@nuxt/eslint/dist/chunks/index.mjs:345:5)
    at async setupConfigGen (node_modules/.pnpm/@nuxt+eslint@1.16.0_@typescript-eslint+utils@8.63.0_eslint@10.7.0_jiti@2.7.0__typescrip_604ac62b52ff7976c76bdfa3480d1261/node_modules/@nuxt/eslint/dist/chunks/index.mjs:350:3)
    at async setup (node_modules/.pnpm/@nuxt+eslint@1.16.0_@typescript-eslint+utils@8.63.0_eslint@10.7.0_jiti@2.7.0__typescrip_604ac62b52ff7976c76bdfa3480d1261/node_modules/@nuxt/eslint/dist/module.mjs:14:7)
    at async normalizedModule (node_modules/.pnpm/@nuxt+kit@4.4.8_magicast@0.5.3/node_modules/@nuxt/kit/dist/index.mjs:169:10)
    at async callModule (node_modules/.pnpm/@nuxt+kit@4.4.8_magicast@0.5.3/node_modules/@nuxt/kit/dist/index.mjs:736:46)
    at async installModules (node_modules/.pnpm/@nuxt+kit@4.4.8_magicast@0.5.3/node_modules/@nuxt/kit/dist/index.mjs:570:3)
    at async initNuxt (node_modules/.pnpm/nuxt@4.4.8_@babel+plugin-syntax-jsx@7.29.7_@babel+core@7.29.7__@babel+plugin-syntax-typ_8f8254dac8cd70f4db0ffdc5cafef09e/node_modules/nuxt/dist/index.mjs:7337:3)
    at async loadNuxt (node_modules/.pnpm/nuxt@4.4.8_@babel+plugin-syntax-jsx@7.29.7_@babel+core@7.29.7__@babel+plugin-syntax-typ_8f8254dac8cd70f4db0ffdc5cafef09e/node_modules/nuxt/dist/index.mjs:7579:5) 


[ELIFECYCLE] Command failed with exit code 1.
exit_code=1
```

### vitest
```
$ pnpm test
    "imports",
-   "cte-onboarding",
  ]

 ❯ tests/unit/navigation.test.ts:77:77
     75|       'docs-catalog'
     76|     ])
     77|     expect(tree.find(d => d.id === 'operations')?.children?.map(c => c…
       |                                                                             ^
     78|       'health',
     79|       'exports',

⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯[2/3]⎯

 FAIL  tests/unit/navigation.test.ts > navigation > toNavigationItems gera trigger com children no estilo template Settings
AssertionError: expected 5 to be 6 // Object.is equality

- Expected
+ Received

- 6
+ 5

 ❯ tests/unit/navigation.test.ts:128:35
    126|     const ops = items.find(i => i.label === 'Operações')
    127|     expect(ops?.type).toBe('trigger')
    128|     expect(ops?.children?.length).toBe(6)
       |                                   ^
    129|     expect(ops?.to).toBeUndefined()
    130|   })

⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯[3/3]⎯


 Test Files  2 failed | 20 passed (22)
      Tests  3 failed | 199 passed (202)
   Start at  22:24:03
   Duration  13.29s (transform 7.05s, setup 0ms, import 10.93s, tests 1.61s, environment 3ms)

[ELIFECYCLE] Test failed. See above for more details.
exit_code=1
```

### artifacts
```
$ pnpm test:artifacts
$ node tests/security/scan-artifacts.mjs
Varredura concluída em 5 raiz(es), sem material sensível.
exit_code=0
```

### build
```
$ pnpm build
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/D1NmtnDU.js              17.02 kB │ gzip:   5.59 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/DDgnSxCO.js              17.10 kB │ gzip:   5.75 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/ImLM3Sn3.js              17.39 kB │ gzip:   5.76 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/nCpIpiPY.js              17.96 kB │ gzip:   4.76 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/CE_hL6PX.js              18.81 kB │ gzip:   6.57 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/BPY-Bo5t.js              18.91 kB │ gzip:   6.11 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/BaimF5fE.js              20.70 kB │ gzip:   7.42 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/zfkjCdW3.js              22.47 kB │ gzip:   6.80 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/Eb2Q-twx.js              25.15 kB │ gzip:   8.33 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/B_UZkjU9.js              27.13 kB │ gzip:   9.18 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/CFwjgN9w.js              28.25 kB │ gzip:   8.14 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/nk17ORvW.js              28.88 kB │ gzip:  10.92 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/DoFl6TfD.js              33.56 kB │ gzip:  10.18 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/Y92uthsA.js              40.23 kB │ gzip:  11.33 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/B49Mg4OH.js              40.48 kB │ gzip:  10.57 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/C7PF8GFE.js              47.21 kB │ gzip:  13.79 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/CIiv9HPr.js              50.92 kB │ gzip:  12.62 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/CH3dRSXA.js              57.03 kB │ gzip:  11.41 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/CaS5nkjV.js              67.48 kB │ gzip:  19.07 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/C4hhM-gn.js              69.90 kB │ gzip:  18.78 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/CkfPZKrc.js              75.07 kB │ gzip:  22.18 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/rgSlZ5Of.js             103.08 kB │ gzip:  32.49 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/D9zGdDsW.js             238.78 kB │ gzip:  76.33 kB
ℹ ../node_modules/.cache/nuxt/.nuxt/dist/client/_nuxt/D8y0d2A3.js             440.94 kB │ gzip: 152.76 kB
ℹ ✓ built in 2m 43s
✔ Client built in 163030ms
ℹ Building server...
ℹ vite v7.3.6 building ssr environment for production...
ℹ transforming...
ℹ ✓ 1 modules transformed.
ℹ rendering chunks...
ℹ ✓ built in 77ms
✔ Server built in 174ms

 ERROR  EACCES: permission denied, unlink '/home/obsidian/dev/app/frontend/.output/nitro.json'

   


[ELIFECYCLE] Command failed with exit code 1.
exit_code=1
```

### playwright_smoke
```
$ pnpm exec playwright test tests/e2e/smoke.spec.ts --reporter=line
(Use `node --trace-warnings ...` to show where the warning was created)

[8/9] [minimum-360] › tests/e2e/smoke.spec.ts:17:3 › superfície pública e shell › rotas autenticadas redirecionam para login
  4) [minimum-360] › tests/e2e/smoke.spec.ts:8:3 › superfície pública e shell › login é acessível e não mostra material sensível 

    Error: expect(locator).toBeVisible() failed

    Locator: getByRole('button', { name: 'Entrar' })
    Expected: visible
    Timeout: 20000ms
    Error: element(s) not found

    Call log:
      - Expect "toBeVisible" with timeout 20000ms
      - waiting for getByRole('button', { name: 'Entrar' })


       9 |     await page.goto('/login')
      10 |     // Desktop: heading "Entrar no painel" é lg:hidden; o submit "Entrar" é o controle estável
    > 11 |     await expect(page.getByRole('button', { name: 'Entrar' })).toBeVisible()
         |                                                                ^
      12 |     const body = await page.locator('body').innerText()
      13 |     expect(body).not.toMatch(/BEGIN (RSA |EC )?PRIVATE KEY/)
      14 |     expect(body).not.toMatch(/<\?xml[\s\S]*InfNFSe/)
        at /home/obsidian/dev/app/frontend/tests/e2e/smoke.spec.ts:11:64

    Error Context: test-results/smoke-superfície-pública-e-51eb6-ão-mostra-material-sensível-minimum-360/error-context.md



(node:2859210) Warning: The 'NO_COLOR' env is ignored due to the 'FORCE_COLOR' env being set.
(Use `node --trace-warnings ...` to show where the warning was created)

[9/9] [minimum-360] › tests/e2e/smoke.spec.ts:24:3 › viewport e overflow › login não exige rolagem horizontal a 360px
  4 failed
    [desktop-1440] › tests/e2e/smoke.spec.ts:8:3 › superfície pública e shell › login é acessível e não mostra material sensível 
    [desktop-1440] › tests/e2e/smoke.spec.ts:17:3 › superfície pública e shell › rotas autenticadas redirecionam para login 
    [mobile-390] › tests/e2e/smoke.spec.ts:17:3 › superfície pública e shell › rotas autenticadas redirecionam para login 
    [minimum-360] › tests/e2e/smoke.spec.ts:8:3 › superfície pública e shell › login é acessível e não mostra material sensível 
  5 passed (1.6m)
exit_code=1
```

