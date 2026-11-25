// Syntax only; this is NOT a full typecheck with installed package typings.
const fs=require('node:fs'),path=require('node:path'),ts=require('typescript');
let count=0,errors=0;
function walk(p){for(const entry of fs.readdirSync(p,{withFileTypes:true})){if(['node_modules','dist','vendor'].includes(entry.name))continue;const f=path.join(p,entry.name);if(entry.isDirectory()){walk(f);continue;}if(!/\.tsx?$/.test(f)||f.endsWith('.d.ts'))continue;const result=ts.transpileModule(fs.readFileSync(f,'utf8'),{fileName:f,reportDiagnostics:true,compilerOptions:{target:ts.ScriptTarget.ES2022,module:ts.ModuleKind.ESNext,jsx:ts.JsxEmit.ReactJSX,isolatedModules:true}});count++;for(const d of result.diagnostics||[]){if(d.category===ts.DiagnosticCategory.Error){console.error(f,ts.flattenDiagnosticMessageText(d.messageText,'\n'));errors++;}}}}
walk('frontend');walk('backend-node');console.log(`${errors?'FAIL':'PASS'}: ${count} TypeScript/TSX syntax files, ${errors} errors`);process.exitCode=errors?1:0;
