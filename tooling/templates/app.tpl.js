const { Command } = require('commander');
const program = new Command();

program
  .name('InVRT')
  .description('{{app.Info.description}}')
  .version('{{app.Info.version}}');

{{#each app.Commands}}

  program.command('{{@key}}')
    .description('{{this.description}}')
    {{#each @root.app.Sections}}
      .option('--{{@key}} <{{@key}}>', '{{this.description}}', '{{this.default}}')
    {{/each}}
    .action(() => {
      console.log('Executing {{@key}} command');
      {{#each this.directories}}
        console.log('Processing directory: {{this.default}}');
      {{/each}}
      console.log('Writing file {{this.output_file}}');
      console.log('{{this.success}}');
    });
{{/each}}

program.parse();
