# Astro/Shadcn component driven report

Use Astro, Tailwind for a Playwright reporter, you should think of it as a two-step process: building an Astro site that serves as your report UI, and then having your Playwright reporter "hydrate" that site with test data.
This is the "cleanest" way to build a professional reporter because Astro handles the component logic and CSS bundling, leaving you with a single static folder (or file) to host.
------------------------------

## 🏗️ 1. Setup the Astro Report Project
First, create a dedicated Astro project that will act as your "Reporter Engine."

# Create the astro project
npm create astro@latest ./playwright-report-ui -- --template minimal

# Add Tailwind and ShadCN
npx astro add tailwind
npx shadcn@latest init --preset b7C9rv8KW --template astro

------------------------------
## 📄 2. Create the Report Component
In src/pages/index.astro, you define how the report looks. Since Astro is server-side rendered by default, you can pass test data into it as a JSON object.

---
// index.astro
import "../styles/global.css";
// In a real reporter, you'd pass this JSON during the build
import results from "../data/test-results.json"; 
---

<html lang="en" data-theme="light">
  <head><title>Astro Test Report</title></head>
  <body class="p-10 bg-base-200">
    <div class="max-w-4xl mx-auto space-y-6">
      <h1 class="text-4xl font-bold">Playwright Results</h1>
      
      <div class="stats shadow bg-base-100 w-full">
        <div class="stat">
          <div class="stat-title">Passed</div>
          <div class="stat-value text-success">{results.passed}</div>
        </div>
        <div class="stat">
          <div class="stat-title">Failed</div>
          <div class="stat-value text-error">{results.failed}</div>
        </div>
      </div>

      <div class="space-y-2">
        {results.tests.map((test) => (
          <div class={`collapse collapse-arrow bg-base-100 border ${test.status === 'failed' ? 'border-error' : 'border-base-300'}`}>
            <input type="radio" name="test-accordion" /> 
            <div class="collapse-title font-medium flex justify-between">
              <span>{test.title}</span>
              <span class={test.status === 'failed' ? 'text-error' : 'text-success'}>{test.status}</span>
            </div>
            <div class="collapse-content text-sm opacity-70">
              <p>Duration: {test.duration}ms</p>
              {test.error && <pre class="bg-neutral p-4 rounded text-error-content mt-2">{test.error}</pre>}
            </div>
          </div>
        ))}
      </div>
    </div>
  </body>
</html>

------------------------------
## 🚀 3. Connecting to Playwright

In the Playwright CustomReporter.js, trigger the Astro build and pass the live results into that directory before it compiles.

// my-reporter.jsimport { execSync } from 'child_process';import fs from 'fs';
class MyAstroReporter {
  async onEnd(result) {
    const summary = {
      passed: result.stats.expected,
      failed: result.stats.unexpected,
      tests: result.suites.flatMap(s => s.allTests().map(t => ({
        title: t.title,
        status: t.outcome(),
        duration: t.results[0]?.duration
      })))
    };

    // 2. Write data to the Astro project's data folder
    fs.writeFileSync('./playwright-report-ui/src/data/test-results.json', JSON.stringify(summary));

    // 3. Trigger Astro build to generate the final HTML
    console.log('Building Astro report...');
    execSync('npm run build', { cwd: './playwright-report-ui' });
    
    console.log('Report generated in ./playwright-report-ui/dist/index.html');
  }
}
export default MyAstroReporter;

------------------------------
## ✅ Why this is better

* Component Logic: You can use Astro components (like <TestCard />) to keep the code clean.
* Tailwind Power: You get full IDE support for Tailwind classes while designing.
* Static Output: The final dist/index.html is a standalone file that doesn't need a server.

If you'd like, I can show you how to inline all the CSS and JS into that single HTML file so it's easier to send via email or Slack. Would that be useful?

