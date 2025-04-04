# Módulo de Carrier para CWS (Correios)

Crie cotações de entrega diretamente na API do CWS (Correios), com tabela de contingência para casos de queda da API. Além disso, tenha acompanhamento com tracking page e email ao cliente para acompanhamento de entregas.

## Badges
[![Magento - Coding Quality](https://github.com/elisei/sigep-web-carrier/actions/workflows/magento-coding-quality.yml/badge.svg)](https://github.com/elisei/sigep-web-carrier/actions/workflows/magento-coding-quality.yml)
[![Magento - Mess Detector](https://github.com/elisei/sigep-web-carrier/actions/workflows/mess-detector.yml/badge.svg)](https://github.com/elisei/sigep-web-carrier/actions/workflows/mess-detector.yml)
[![Magento - Php Stan](https://github.com/elisei/sigep-web-carrier/actions/workflows/phpstan.yml/badge.svg)](https://github.com/elisei/sigep-web-carrier/actions/workflows/phpstan.yml)

## Sobre o Módulo

O módulo Correios CWS para Magento 2 oferece uma solução completa para integração com os serviços dos Correios do Brasil. Desenvolvido pela O2TI, este módulo utiliza a nova API CWS (também conhecida como PPN) dos Correios, substituindo a antiga API SigepWeb e permitindo o cálculo preciso de fretes, geração de etiquetas de envio e gestão de PLPs (Pré-Lista de Postagem).

## Compatibilidade

- Magento 2.4.x ou superior
- PHP 7.4 ou superior
- Extensão SOAP do PHP habilitada
- Contrato ativo com os Correios (para utilização em produção)

## Recursos

- **Cotação de envio**: Cálculo de frete em tempo real com a API CWS
- **Múltiplos Serviços**: Suporte a todos os serviços disponíveis no contrato (SEDEX, PAC, etc.)
- **Tabela de contingência dinâmica**: Funcionamento garantido mesmo quando a API dos Correios estiver fora do ar
- **Email de atualização de entrega**: Notificação automática para seus clientes
- **Página de acompanhamento de entrega**: Interface para o cliente acompanhar o status do pedido
- **Geração de Etiqueta de Envio**: Impressão de etiquetas no formato oficial dos Correios
- **Gestão de PPN**: Criação e gerenciamento de Pré-Listas de Postagem
- **Impressão em Lote**: Imprima múltiplas etiquetas de uma só vez
- **Configuração de Frete Grátis**: Criação de regras por preço, região ou SKU

## Documentação Completa

Visite nossa [Wiki](../../wiki) para documentação detalhada sobre:

- [Guia de Instalação Completo](../../wiki/Instalação)
- [Guia de Configuração Detalhado](../../wiki/Configuração)
- [Gerenciamento de Etiquetas e PLPs](../../wiki/Gerenciamento-de-Etiquetas)
- [FAQ Completa](../../wiki/FAQ)

## Principais Benefícios

- **Integração Completa**: Todos os serviços necessários em um único módulo
- **Fácil Configuração**: Interface intuitiva para configuração e gerenciamento
- **Precisão nos Cálculos**: Cálculos precisos de frete conforme especificações dos Correios
- **Automação de Processos**: Automatiza a geração de documentação e etiquetas
- **Modo de Contingência**: Garantia de funcionamento mesmo quando a API dos Correios estiver fora do ar

## Suporte

Para obter suporte ou relatar problemas:

- **GitHub**: [Abrir uma Issue](../../issues)
- **E-mail**: suporte@o2ti.com
- **Site**: [https://o2ti.com](https://o2ti.com)

## Contribuição

Contribuições são bem-vindas! Sinta-se à vontade para enviar um Pull Request.

## Licença

[Open Source License](../../LICENSE)