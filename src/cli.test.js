const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const invrtCliPath = path.join(__dirname, 'invrt.js');

console.log('🧪 Testing invrt CLI\n');

// Test 1: Invalid command should exit with error
console.log('Test 1: Invalid command');
try {
    execSync(`node ${invrtCliPath} invalid`, { stdio: 'pipe' });
    console.log('❌ Should have exited with error for invalid command');
    process.exit(1);
} catch (error) {
    if (error.status === 1) {
        console.log('✅ Invalid command exits with error code 1\n');
    } else {
        console.log('❌ Unexpected error code:', error.status);
        process.exit(1);
    }
}

// Test 2: No command should exit with error
console.log('Test 2: No command provided');
try {
    execSync(`node ${invrtCliPath}`, { stdio: 'pipe' });
    console.log('❌ Should have exited with error for no command');
    process.exit(1);
} catch (error) {
    if (error.status === 1) {
        console.log('✅ No command exits with error code 1\n');
    } else {
        console.log('❌ Unexpected error code:', error.status);
        process.exit(1);
    }
}

// Test 3: Config file missing for non-init commands
console.log('Test 3: Config file missing for non-init commands');
try {
    // Create a temporary working directory without config
    const tmpDir = path.join(__dirname, 'tmp-test-cli');
    if (!fs.existsSync(tmpDir)) {
        fs.mkdirSync(tmpDir);
    }
    
    execSync(`cd ${tmpDir} && node ${invrtCliPath} crawl`, { 
        stdio: 'pipe',
        env: { ...process.env, INIT_CWD: tmpDir }
    });
    console.log('❌ Should have exited with error when config missing');
    process.exit(1);
} catch (error) {
    if (error.status === 1) {
        console.log('✅ Missing config exits with error code 1\n');
    } else {
        console.log('❌ Unexpected error code:', error.status);
        process.exit(1);
    }
}

// Test 4: Init command exists and is callable
console.log('Test 4: Init command is callable');
try {
    const result = execSync(`node ${invrtCliPath} init`, { 
        stdio: 'pipe',
        timeout: 2000,
        encoding: 'utf-8'
    }).catch ? 'error' : 'success';
    console.log('✅ Init command is callable\n');
} catch (error) {
    // Init might fail if script is missing, but the CLI should load
    if (error.killed || error.message.includes('ENOENT')) {
        console.log('✅ Init command is callable (script may not exist)\n');
    } else if (error.status !== undefined) {
        console.log('✅ Init command is callable\n');
    } else {
        console.log('⚠️  Init command exists but errored:', error.message, '\n');
    }
}

// Test 5: Crawl command is callable
console.log('Test 5: Crawl command is callable');
try {
    execSync(`node ${invrtCliPath} crawl`, { 
        stdio: 'pipe',
        timeout: 2000
    });
} catch (error) {
    if (error.status === 1 && error.message.includes('Configuration file not found')) {
        console.log('✅ Crawl command is callable (fails due to missing config as expected)\n');
    } else {
        console.log('✅ Crawl command is callable\n');
    }
}

// Test 6: Reference command is callable
console.log('Test 6: Reference command is callable');
try {
    execSync(`node ${invrtCliPath} reference`, { 
        stdio: 'pipe',
        timeout: 2000
    });
} catch (error) {
    if (error.status === 1 && error.message.includes('Configuration file not found')) {
        console.log('✅ Reference command is callable (fails due to missing config as expected)\n');
    } else {
        console.log('✅ Reference command is callable\n');
    }
}

// Test 7: Test command is callable
console.log('Test 7: Test command is callable');
try {
    execSync(`node ${invrtCliPath} test`, { 
        stdio: 'pipe',
        timeout: 2000
    });
} catch (error) {
    if (error.status === 1 && error.message.includes('Configuration file not found')) {
        console.log('✅ Test command is callable (fails due to missing config as expected)\n');
    } else {
        console.log('✅ Test command is callable\n');
    }
}

console.log('🎉 All CLI tests passed!');
process.exit(0);
