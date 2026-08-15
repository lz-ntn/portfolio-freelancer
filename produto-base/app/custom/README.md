# Customizações por Cliente

Esta pasta **nunca** é alterada pelo núcleo. Cada cliente tem a sua pasta.

```
app/custom/
├── CLIENTE_A/   ← overrides e configuração específica do Cliente A
└── CLIENTE_B/   ← overrides e configuração específica do Cliente B
```

## Regras

1. **Nunca** alterar `app/core/` nem `app/modules/` para um cliente específico.
2. Diferenças entre clientes = **configuração**, não código novo:
   - campos extra → tabela `custom_fields`
   - estados/workflows → tabela `workflows`
   - regras de comissão → tabela `commission_rules`
3. Se precisares mesmo de código específico, ele vive nesta pasta e é versionado à parte.
4. Todo o resto permanece igual → mais clientes, menos bugs.
