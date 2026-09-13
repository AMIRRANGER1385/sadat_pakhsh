// Passenger/cPanel startup entry. Use npm run build first.
process.env.NODE_ENV='production';
const http=require('node:http');
const next=require('next');
const app=next({dev:false,dir:__dirname});
const handler=app.getRequestHandler();
app.prepare().then(()=>{
 const server=http.createServer((req,res)=>{handler(req,res).catch(()=>{if(!res.headersSent){res.statusCode=500;res.end('Internal server error')}else res.end()})});
 server.requestTimeout=30000;
 const port=process.env.PORT||'3000';
 server.listen(/^\d+$/.test(port)?Number(port):port);
}).catch(()=>{console.error('Application startup failed. Check build, environment and database configuration.');process.exit(1)});
