import { Module } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { ThrottlerModule } from '@nestjs/throttler';
import * as Joi from 'joi';

import { PrismaModule } from './services/prisma/prisma.module';
import { AuthModule } from './modules/auth/auth.module';
import { BoardsModule } from './modules/boards/boards.module';
import { ThreadsModule } from './modules/threads/threads.module';
import { PostsModule } from './modules/posts/posts.module';
import { FilesModule } from './modules/files/files.module';
import { ModerationModule } from './modules/moderation/moderation.module';
import { CaptchaModule } from './modules/captcha/captcha.module';
import { HealthModule } from './modules/health/health.module';

@Module({
  imports: [
    // Carga y validación de configuración
    ConfigModule.forRoot({
      isGlobal: true,
      validationSchema: Joi.object({
        NODE_ENV: Joi.string()
          .valid('development', 'production', 'test')
          .default('development'),
        PORT: Joi.number().default(3000),
        API_PREFIX: Joi.string().default('api'),
        API_VERSION: Joi.string().default('v1'),
        DATABASE_URL: Joi.string().required(),
        JWT_SECRET: Joi.string().required(),
        JWT_EXPIRES_IN: Joi.string().default('7d'),
        JWT_REFRESH_SECRET: Joi.string().required(),
        JWT_REFRESH_EXPIRES_IN: Joi.string().default('30d'),
        RATE_LIMIT_WINDOW: Joi.number().default(15),
        RATE_LIMIT_MAX: Joi.number().default(100),
        CORS_ORIGINS: Joi.string().default(''),
      }),
    }),

    // Rate limiting
    ThrottlerModule.forRootAsync({
      imports: [ConfigModule],
      inject: [ConfigService],
      useFactory: (config: ConfigService) => ({
        ttl: config.get<number>('RATE_LIMIT_WINDOW', 15),
        limit: config.get<number>('RATE_LIMIT_MAX', 100),
      }),
    }),

    // Servicios core
    PrismaModule,
    HealthModule,
    
    // Módulos funcionales
    AuthModule,
    BoardsModule,
    ThreadsModule,
    PostsModule,
    FilesModule,
    ModerationModule,
    CaptchaModule,
  ],
})
export class AppModule {}
