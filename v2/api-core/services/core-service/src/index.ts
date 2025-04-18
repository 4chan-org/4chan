import { NestFactory } from '@nestjs/core';
import { ValidationPipe } from '@nestjs/common';
import { SwaggerModule, DocumentBuilder } from '@nestjs/swagger';
import helmet from 'helmet';
import { AppModule } from './app';
import { ConfigService } from '@nestjs/config';
import { Logger } from './utils/logger';

async function bootstrap() {
  const app = await NestFactory.create(AppModule, {
    logger: ['error', 'warn', 'log', 'debug', 'verbose'],
  });

  const configService = app.get(ConfigService);
  const port = configService.get<number>('PORT', 3000);
  const apiPrefix = configService.get<string>('API_PREFIX', 'api');
  const apiVersion = configService.get<string>('API_VERSION', 'v1');
  const swaggerPath = configService.get<string>('SWAGGER_PATH', 'docs');
  const isDevelopment = configService.get<string>('NODE_ENV') === 'development';

  // Seguridad
  app.use(
    helmet({
      contentSecurityPolicy: isDevelopment ? false : undefined,
    }),
  );

  // CORS
  const corsOrigins = configService.get<string>('CORS_ORIGINS')?.split(',') || [];
  app.enableCors({
    origin: corsOrigins.length ? corsOrigins : true,
    methods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
    credentials: true,
  });

  // Prefijo global para todas las rutas
  app.setGlobalPrefix(`${apiPrefix}/${apiVersion}`);

  // Pipes globales
  app.useGlobalPipes(
    new ValidationPipe({
      whitelist: true,
      forbidNonWhitelisted: true,
      transform: true,
    }),
  );

  // Swagger
  if (isDevelopment) {
    const config = new DocumentBuilder()
      .setTitle('4chan API')
      .setDescription('API moderna para 4chan')
      .setVersion(apiVersion)
      .addBearerAuth()
      .build();

    const document = SwaggerModule.createDocument(app, config);
    SwaggerModule.setup(swaggerPath, app, document);
  }

  await app.listen(port);
  Logger.log(`Servidor corriendo en: http://localhost:${port}/${apiPrefix}/${apiVersion}`);
  if (isDevelopment) {
    Logger.log(`Documentación Swagger disponible en: http://localhost:${port}/${swaggerPath}`);
  }
}

bootstrap().catch((err) => {
  Logger.error(`Error al iniciar la aplicación: ${err.message}`, err.stack);
  process.exit(1);
});
